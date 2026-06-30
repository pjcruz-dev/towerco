<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementRfqStatus
{
    public const DRAFT = 'draft';

    public const OPEN = 'open';

    public const CLOSED = 'closed';

    public const AWARDED = 'awarded';

    public const CONVERTED = 'converted';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::OPEN,
            self::CLOSED,
            self::AWARDED,
            self::CONVERTED,
            self::CANCELLED,
        ];
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::DRAFT;
    }

    public static function acceptsBids(string $status): bool
    {
        return $status === self::OPEN;
    }

    /** @return list<string> */
    public static function activeForSourcing(): array
    {
        return [
            self::DRAFT,
            self::OPEN,
            self::CLOSED,
            self::AWARDED,
        ];
    }

    public static function isActiveForSourcing(string $status): bool
    {
        return in_array($status, self::activeForSourcing(), true);
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::OPEN => __('Open for quotes'),
            self::CLOSED => __('Closed'),
            self::AWARDED => __('Awarded'),
            self::CONVERTED => __('Converted to PO'),
            self::CANCELLED => __('Cancelled'),
            default => $status,
        };
    }
}
