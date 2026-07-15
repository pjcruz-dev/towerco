<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementExpenseType
{
    public const CAPEX = 'capex';

    public const OPEX = 'opex';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::CAPEX, self::OPEX];
    }

    public static function isValid(?string $type): bool
    {
        return $type !== null && in_array($type, self::all(), true);
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::CAPEX => __('CAPEX'),
            self::OPEX => __('OPEX'),
            default => $type,
        };
    }
}
