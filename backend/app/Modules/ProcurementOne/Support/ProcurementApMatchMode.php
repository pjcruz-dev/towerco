<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementApMatchMode
{
    public const TWO_WAY = 'two_way';

    public const THREE_WAY = 'three_way';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::TWO_WAY, self::THREE_WAY];
    }

    public static function isValid(?string $mode): bool
    {
        return $mode !== null && in_array($mode, self::all(), true);
    }

    public static function label(string $mode): string
    {
        return match ($mode) {
            self::TWO_WAY => __('2-way (PO)'),
            self::THREE_WAY => __('3-way (PO + GRN)'),
            default => $mode,
        };
    }
}
