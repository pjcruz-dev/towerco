<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementApInvoiceStatus
{
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const CANCELLED = 'cancelled';

    public const VOIDED = 'voided';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::CANCELLED,
            self::VOIDED,
        ];
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::DRAFT;
    }

    public static function fromEApprovalStatus(string $eApprovalStatus): string
    {
        return match ($eApprovalStatus) {
            'draft' => self::DRAFT,
            'pending', 'returned', 'awaiting_dcf' => self::PENDING_APPROVAL,
            'approved' => self::APPROVED,
            'rejected', 'cancelled' => self::CANCELLED,
            default => self::PENDING_APPROVAL,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::PENDING_APPROVAL => __('Pending approval'),
            self::APPROVED => __('Approved for payment'),
            self::CANCELLED => __('Cancelled'),
            self::VOIDED => __('Voided'),
            default => $status,
        };
    }
}
