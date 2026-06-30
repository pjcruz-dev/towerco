<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementGrnStatus
{
    public const DRAFT = 'draft';

    public const POSTED = 'posted';

    public const CANCELLED = 'cancelled';

    public const VOIDED = 'voided';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::POSTED,
            self::CANCELLED,
            self::VOIDED,
        ];
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::DRAFT;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::POSTED => __('Posted'),
            self::CANCELLED => __('Cancelled'),
            self::VOIDED => __('Voided'),
            default => $status,
        };
    }
}
