<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Models\PlatformBillingSetting;
use Illuminate\Validation\ValidationException;

final class PlatformBillingCurrencyCatalog
{
    /**
     * @return array<string, string> ISO code => label
     */
    public static function supported(): array
    {
        /** @var array<string, string> $configured */
        $configured = config('billing.currencies', []);

        return $configured !== [] ? $configured : [
            'USD' => 'US Dollar',
            'PHP' => 'Philippine Peso',
        ];
    }

    /**
     * @return list<array{code: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (string $code, string $label): array => ['code' => $code, 'label' => $label],
            array_keys(self::supported()),
            array_values(self::supported()),
        );
    }

    public static function isSupported(string $code): bool
    {
        $normalized = strtoupper(trim($code));

        return $normalized !== '' && array_key_exists($normalized, self::supported());
    }

    public static function normalize(string $code): string
    {
        $normalized = strtoupper(trim($code));

        if (! self::isSupported($normalized)) {
            throw ValidationException::withMessages([
                'currency' => [__('Select a supported billing currency.')],
            ]);
        }

        return $normalized;
    }

    public static function platformCurrency(): string
    {
        $settings = PlatformBillingSetting::singleton();
        $stored = strtoupper(trim((string) ($settings->currency ?? '')));

        if ($stored !== '' && self::isSupported($stored)) {
            return $stored;
        }

        $fallback = strtoupper(trim((string) config('billing.revenue.currency', 'USD')));

        return self::isSupported($fallback) ? $fallback : 'USD';
    }
}
