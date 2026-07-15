<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementInventoryLocationKind
{
    public const WAREHOUSE = 'warehouse';

    public const TOWER = 'tower';

    public const SITE = 'site';

    public const CENTRAL = 'central';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [self::WAREHOUSE, self::TOWER, self::SITE, self::CENTRAL];
    }

    public static function isValid(string $kind): bool
    {
        return in_array($kind, self::all(), true);
    }

    public static function label(string $kind): string
    {
        return match ($kind) {
            self::WAREHOUSE => __('Warehouse'),
            self::TOWER => __('Tower'),
            self::SITE => __('Site'),
            self::CENTRAL => __('Central warehouse'),
            default => $kind,
        };
    }
}
