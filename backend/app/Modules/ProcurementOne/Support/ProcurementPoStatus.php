<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementPoStatus
{
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const SENT = 'sent';

    public const PARTIALLY_RECEIVED = 'partially_received';

    public const RECEIVED = 'received';

    public const CLOSED = 'closed';

    public const CANCELLED = 'cancelled';

    public const VOIDED = 'voided';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::SENT,
            self::PARTIALLY_RECEIVED,
            self::RECEIVED,
            self::CLOSED,
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
            'rejected' => self::CANCELLED,
            'cancelled' => self::CANCELLED,
            default => self::PENDING_APPROVAL,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::PENDING_APPROVAL => __('Pending approval'),
            self::APPROVED => __('Approved'),
            self::SENT => __('Sent to vendor'),
            self::PARTIALLY_RECEIVED => __('Partially received'),
            self::RECEIVED => __('Received'),
            self::CLOSED => __('Closed'),
            self::CANCELLED => __('Cancelled'),
            self::VOIDED => __('Voided'),
            default => $status,
        };
    }
}
