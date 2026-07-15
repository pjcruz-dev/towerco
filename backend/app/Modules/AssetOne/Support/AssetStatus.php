<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Support;

final class AssetStatus
{
    public const IN_WAREHOUSE = 'in_warehouse';

    public const IN_TRANSIT = 'in_transit';

    public const DEPLOYED = 'deployed';

    public const MAINTENANCE = 'maintenance';

    public const DISPOSED = 'disposed';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::IN_WAREHOUSE,
            self::IN_TRANSIT,
            self::DEPLOYED,
            self::MAINTENANCE,
            self::DISPOSED,
        ];
    }

    public static function isValid(string $status): bool
    {
        return in_array($status, self::all(), true);
    }

    /**
     * @return list<string>
     */
    public static function allowedTransitions(string $from): array
    {
        return match ($from) {
            self::IN_WAREHOUSE => [self::IN_TRANSIT, self::DEPLOYED, self::MAINTENANCE, self::DISPOSED],
            self::IN_TRANSIT => [self::IN_WAREHOUSE, self::DEPLOYED, self::MAINTENANCE],
            self::DEPLOYED => [self::MAINTENANCE, self::IN_TRANSIT, self::DISPOSED],
            self::MAINTENANCE => [self::IN_WAREHOUSE, self::DEPLOYED, self::DISPOSED],
            self::DISPOSED => [],
            default => [],
        };
    }
}
