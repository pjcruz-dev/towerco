<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementExportEntity
{
    public const VENDORS = 'vendors';

    public const PRS = 'prs';

    public const PR_LINES = 'pr_lines';

    public const POS = 'pos';

    public const PO_LINES = 'po_lines';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::VENDORS,
            self::PRS,
            self::PR_LINES,
            self::POS,
            self::PO_LINES,
        ];
    }

    public static function isValid(string $entity): bool
    {
        return in_array($entity, self::all(), true);
    }

    public static function label(string $entity): string
    {
        return match ($entity) {
            self::VENDORS => 'Vendors',
            self::PRS => 'Purchase requisitions',
            self::PR_LINES => 'PR lines',
            self::POS => 'Purchase orders',
            self::PO_LINES => 'PO lines',
            default => $entity,
        };
    }

    public static function sheetName(string $entity): string
    {
        return match ($entity) {
            self::VENDORS => 'Vendors',
            self::PRS => 'PRs',
            self::PR_LINES => 'PR Lines',
            self::POS => 'POs',
            self::PO_LINES => 'PO Lines',
            default => substr($entity, 0, 31),
        };
    }
}
