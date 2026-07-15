<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementPrStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const VOIDED = 'voided';

    public const CONVERTED = 'converted';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::REJECTED,
            self::CANCELLED,
            self::VOIDED,
            self::CONVERTED,
        ];
    }

    public static function isEditable(string $status): bool
    {
        return $status === self::DRAFT;
    }

    public static function fromEApprovalStatus(string $eApprovalStatus, bool $hasPoChild = false): string
    {
        if ($hasPoChild && $eApprovalStatus === 'approved') {
            return self::CONVERTED;
        }

        return match ($eApprovalStatus) {
            'draft' => self::DRAFT,
            'pending', 'returned', 'awaiting_dcf' => self::PENDING_APPROVAL,
            'approved' => self::APPROVED,
            'rejected' => self::REJECTED,
            'cancelled' => self::CANCELLED,
            default => self::PENDING_APPROVAL,
        };
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
            self::PENDING_APPROVAL => __('Pending approval'),
            self::APPROVED => __('Approved'),
            self::REJECTED => __('Rejected'),
            self::CANCELLED => __('Cancelled'),
            self::VOIDED => __('Voided'),
            self::CONVERTED => __('Converted to PO'),
            default => $status,
        };
    }
}
