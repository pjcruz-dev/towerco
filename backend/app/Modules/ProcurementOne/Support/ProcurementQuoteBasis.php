<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementQuoteBasis
{
    public const ONE_TIME = 'one_time';

    public const MONTHLY = 'monthly';

    public const YEARLY = 'yearly';

    public const MONTHLY_YEARLY = 'monthly_yearly';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::ONE_TIME,
            self::MONTHLY,
            self::YEARLY,
            self::MONTHLY_YEARLY,
        ];
    }

    public static function normalize(mixed $value): string
    {
        $raw = strtolower(trim((string) $value));
        $raw = str_replace([' ', '-'], '_', $raw);

        return match ($raw) {
            'monthly', 'month', 'per_month', 'mo' => self::MONTHLY,
            'yearly', 'year', 'per_year', 'annual', 'annually', 'yr' => self::YEARLY,
            'monthly_yearly', 'monthly+yearly', 'month_year', 'monthly_and_yearly', 'both' => self::MONTHLY_YEARLY,
            'one_time', 'onetime', 'once', 'unit', 'ea', '' => self::ONE_TIME,
            default => in_array($raw, self::all(), true) ? $raw : self::ONE_TIME,
        };
    }

    public static function label(string $basis): string
    {
        return match (self::normalize($basis)) {
            self::MONTHLY => __('Monthly'),
            self::YEARLY => __('Yearly'),
            self::MONTHLY_YEARLY => __('Monthly + Yearly'),
            default => __('One-time'),
        };
    }

    public static function allowsMonthly(string $basis): bool
    {
        $basis = self::normalize($basis);

        return in_array($basis, [self::MONTHLY, self::MONTHLY_YEARLY], true);
    }

    public static function allowsYearly(string $basis): bool
    {
        $basis = self::normalize($basis);

        return in_array($basis, [self::YEARLY, self::MONTHLY_YEARLY], true);
    }

    public static function requiresUnitPrice(string $basis): bool
    {
        return self::normalize($basis) === self::ONE_TIME;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function fromMetadata(?array $metadata): string
    {
        if (! is_array($metadata)) {
            return self::ONE_TIME;
        }

        return self::normalize($metadata['quote_basis'] ?? self::ONE_TIME);
    }

    /**
     * @param  array<string, string>  $row
     */
    public static function fromGridRow(array $row): string
    {
        foreach (['Quote basis', 'quote_basis', 'Billing', 'Billing mode'] as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return self::normalize($value);
            }
        }

        return self::ONE_TIME;
    }

    public static function normalizedAnnualAmount(
        string $basis,
        float $quantity,
        float $unitPrice,
        ?float $monthlyUnitPrice,
        ?float $yearlyUnitPrice,
    ): float {
        $qty = max(0, $quantity);
        $basis = self::normalize($basis);

        return match ($basis) {
            self::MONTHLY => round(max(0, (float) $monthlyUnitPrice) * $qty * 12, 2),
            self::YEARLY => round(max(0, (float) $yearlyUnitPrice) * $qty, 2),
            self::MONTHLY_YEARLY => self::normalizedAnnualAmount(
                (float) ($yearlyUnitPrice ?? 0) > 0 ? self::YEARLY : self::MONTHLY,
                $qty,
                $unitPrice,
                $monthlyUnitPrice,
                $yearlyUnitPrice,
            ),
            default => round(max(0, $unitPrice) * $qty, 2),
        };
    }
}
