<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Models\PlatformBillingSetting;
use App\Models\User;
use App\Modules\Billing\Support\PlatformBillingCurrencyCatalog;
use App\Modules\Billing\Support\PlatformBillingCurrencyConverter;
use Illuminate\Validation\ValidationException;

final class PlatformBillingCatalogService
{
    private const VALID_TIERS = ['starter', 'professional', 'enterprise'];

    public function __construct(
        private readonly PlatformBillingCurrencyConverter $currencyConverter,
    ) {}

    /**
     * @return array{
     *   currency: string,
     *   supported_currencies: list<array{code: string, label: string}>,
     *   default_annual_discount_percent: float,
     *   tiers: list<array<string, mixed>>
     * }
     */
    public function resolvedCatalog(): array
    {
        $settings = PlatformBillingSetting::singleton();
        /** @var array<string, array<string, mixed>> $tierOverrides */
        $tierOverrides = is_array($settings->tier_overrides) ? $settings->tier_overrides : [];

        $tiers = [];
        foreach (self::VALID_TIERS as $tier) {
            $tiers[] = $this->resolveTier($tier, $tierOverrides[$tier] ?? null, $settings);
        }

        usort($tiers, static fn (array $a, array $b): int => $a['sort'] <=> $b['sort']);

        $currency = PlatformBillingCurrencyCatalog::platformCurrency();

        return [
            'currency' => $currency,
            'pricing_base_currency' => $this->currencyConverter->baseCurrency(),
            'exchange_rates' => $this->currencyConverter->rates(),
            'supported_currencies' => PlatformBillingCurrencyCatalog::options(),
            'default_annual_discount_percent' => $this->normalizeDiscountPercent(
                $settings->default_annual_discount_percent,
                (float) config('billing.annual.default_discount_percent', 20),
            ),
            'tiers' => $tiers,
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array{
     *   currency: string,
     *   default_annual_discount_percent: float,
     *   tiers: list<array<string, mixed>>
     * }
     */
    public function update(array $patch, ?User $actor): array
    {
        $settings = PlatformBillingSetting::singleton();

        if (array_key_exists('currency', $patch)) {
            $settings->currency = PlatformBillingCurrencyCatalog::normalize((string) $patch['currency']);
        }

        if (array_key_exists('default_annual_discount_percent', $patch)) {
            $settings->default_annual_discount_percent = $this->assertDiscountPercent(
                $patch['default_annual_discount_percent'],
                'default_annual_discount_percent',
            );
        }

        if (array_key_exists('tiers', $patch) && is_array($patch['tiers'])) {
            /** @var array<string, array<string, mixed>> $existing */
            $existing = is_array($settings->tier_overrides) ? $settings->tier_overrides : [];

            foreach ($patch['tiers'] as $tierPatch) {
                if (! is_array($tierPatch)) {
                    continue;
                }
                $tier = strtolower(trim((string) ($tierPatch['plan_tier'] ?? '')));
                if (! in_array($tier, self::VALID_TIERS, true)) {
                    continue;
                }

                $merged = $existing[$tier] ?? [];
                if (array_key_exists('included', $tierPatch) && is_array($tierPatch['included'])) {
                    $merged['included'] = $this->normalizeIncluded($tierPatch['included'], $merged['included'] ?? []);
                }
                if (array_key_exists('pricing', $tierPatch) && is_array($tierPatch['pricing'])) {
                    $displayCurrency = PlatformBillingCurrencyCatalog::platformCurrency();
                    $pricingUsd = $this->currencyConverter->convertPricingToUsd(
                        $tierPatch['pricing'],
                        $displayCurrency,
                    );
                    $merged['pricing'] = $this->normalizePricing($pricingUsd, $merged['pricing'] ?? []);
                }
                if (array_key_exists('annual_discount_percent', $tierPatch)) {
                    $merged['annual_discount_percent'] = $this->assertDiscountPercent(
                        $tierPatch['annual_discount_percent'],
                        "tiers.{$tier}.annual_discount_percent",
                    );
                }

                $existing[$tier] = $merged === [] ? [] : $merged;
            }

            $settings->tier_overrides = $existing === [] ? null : $existing;
        }

        $settings->save();

        return $this->resolvedCatalog();
    }

    /**
     * @return array<string, float> plan_tier => monthly list price in platform currency
     */
    public function tierListPrices(): array
    {
        $prices = [];
        foreach ($this->resolvedCatalog()['tiers'] as $row) {
            $tier = (string) ($row['plan_tier'] ?? '');
            if ($tier === '') {
                continue;
            }
            /** @var array<string, float> $pricing */
            $pricing = $row['pricing'] ?? [];
            $prices[$tier] = (float) ($pricing['monthly_base_usd'] ?? 0);
        }

        return $prices;
    }

    public function tierIncluded(string $tier, string $key): int
    {
        $catalog = $this->resolvedCatalog();
        foreach ($catalog['tiers'] as $row) {
            if (($row['plan_tier'] ?? '') !== $tier) {
                continue;
            }
            /** @var array<string, int> $included */
            $included = $row['included'] ?? [];

            return max(0, (int) ($included[$key] ?? 0));
        }

        return 0;
    }

    public function effectiveAnnualDiscountPercent(string $tier, ?float $tenantOverride, ?float $platformDefault = null): float
    {
        if ($tenantOverride !== null) {
            return $this->normalizeDiscountPercent($tenantOverride, 0.0);
        }

        $catalog = $this->resolvedCatalog();
        foreach ($catalog['tiers'] as $row) {
            if (($row['plan_tier'] ?? '') === $tier && isset($row['annual_discount_percent'])) {
                return (float) $row['annual_discount_percent'];
            }
        }

        return $this->normalizeDiscountPercent(
            $platformDefault,
            $catalog['default_annual_discount_percent'],
        );
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array<string, mixed>
     */
    private function resolveTier(string $tier, ?array $override, PlatformBillingSetting $settings): array
    {
        /** @var array<string, array<string, mixed>> $configTiers */
        $configTiers = config('billing.plan_tiers', []);
        $config = $configTiers[$tier] ?? $configTiers['starter'] ?? [];

        /** @var array<string, int> $included */
        $included = array_merge(
            [
                'paid_seats' => 0,
                'rfi_units' => 0,
                'storage_gb' => 0,
            ],
            is_array($config['included'] ?? null) ? $config['included'] : [],
            is_array($override['included'] ?? null) ? $override['included'] : [],
        );

        /** @var array<string, float> $pricing */
        $pricing = array_merge(
            [
                'monthly_base_usd' => 0.0,
                'rfi_overage_usd' => 0.0,
                'paid_seat_overage_usd' => 0.0,
            ],
            is_array($config['pricing'] ?? null) ? $config['pricing'] : [],
            is_array($override['pricing'] ?? null) ? $override['pricing'] : [],
        );

        $defaultDiscount = $this->normalizeDiscountPercent(
            $settings->default_annual_discount_percent,
            (float) config('billing.annual.default_discount_percent', 20),
        );
        $tierDiscount = isset($override['annual_discount_percent'])
            ? $this->normalizeDiscountPercent($override['annual_discount_percent'], $defaultDiscount)
            : $defaultDiscount;

        $pricingUsd = [
            'monthly_base_usd' => max(0, (float) ($pricing['monthly_base_usd'] ?? 0)),
            'rfi_overage_usd' => max(0, (float) ($pricing['rfi_overage_usd'] ?? 0)),
            'paid_seat_overage_usd' => max(0, (float) ($pricing['paid_seat_overage_usd'] ?? 0)),
        ];
        $monthlyBaseUsd = $pricingUsd['monthly_base_usd'];
        $annualBaseUsd = round($monthlyBaseUsd * 12 * (1 - ($tierDiscount / 100)), 2);

        $displayCurrency = PlatformBillingCurrencyCatalog::platformCurrency();
        $displayPricing = $this->currencyConverter->convertPricingFromUsd($pricingUsd, $displayCurrency);
        $displayPricing['annual_base_usd'] = $this->currencyConverter->convertFromUsd($annualBaseUsd, $displayCurrency);

        return [
            'plan_tier' => $tier,
            'label' => (string) ($config['label'] ?? ucfirst($tier)),
            'sort' => (int) ($config['sort'] ?? 0),
            'included' => [
                'paid_seats' => max(0, (int) $included['paid_seats']),
                'rfi_units' => max(0, (int) $included['rfi_units']),
                'storage_gb' => max(0, (int) $included['storage_gb']),
            ],
            'pricing' => [
                'monthly_base_usd' => $displayPricing['monthly_base_usd'],
                'annual_base_usd' => $displayPricing['annual_base_usd'],
                'rfi_overage_usd' => $displayPricing['rfi_overage_usd'],
                'paid_seat_overage_usd' => $displayPricing['paid_seat_overage_usd'],
            ],
            'annual_discount_percent' => $tierDiscount,
            'modules' => $config['modules'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $existing
     * @return array<string, int>
     */
    private function normalizeIncluded(array $patch, array $existing): array
    {
        $out = $existing;
        foreach (['paid_seats', 'rfi_units', 'storage_gb'] as $key) {
            if (! array_key_exists($key, $patch)) {
                continue;
            }
            $value = max(0, (int) $patch[$key]);
            if ($key === 'paid_seats' && $value < 1) {
                throw ValidationException::withMessages([
                    "tiers.included.{$key}" => [__('Paid seats must be at least 1.')],
                ]);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<string, mixed>  $existing
     * @return array<string, float>
     */
    private function normalizePricing(array $patch, array $existing): array
    {
        $out = $existing;
        foreach (['monthly_base_usd', 'rfi_overage_usd', 'paid_seat_overage_usd'] as $key) {
            if (! array_key_exists($key, $patch)) {
                continue;
            }
            $out[$key] = max(0, (float) $patch[$key]);
        }

        return $out;
    }

    private function assertDiscountPercent(mixed $value, string $field): float
    {
        if (! is_numeric($value)) {
            throw ValidationException::withMessages([
                $field => [__('Discount percent must be a number.')],
            ]);
        }

        $percent = (float) $value;
        if ($percent < 0 || $percent > 80) {
            throw ValidationException::withMessages([
                $field => [__('Discount percent must be between 0 and 80.')],
            ]);
        }

        return round($percent, 2);
    }

    private function normalizeDiscountPercent(mixed $value, float $fallback): float
    {
        if (! is_numeric($value)) {
            return round($fallback, 2);
        }

        $percent = (float) $value;

        return round(max(0, min(80, $percent)), 2);
    }
}
