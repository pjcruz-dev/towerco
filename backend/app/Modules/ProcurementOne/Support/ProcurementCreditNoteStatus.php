<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementCreditNoteStatus
{
    public const DRAFT = 'draft';

    public const APPROVED = 'approved';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::DRAFT, self::APPROVED, self::CANCELLED];
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::DRAFT;
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::APPROVED => __('Approved'),
            self::CANCELLED => __('Cancelled'),
            default => $status,
        };
    }
}
