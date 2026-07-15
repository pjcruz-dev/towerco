<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementPaymentRequestStatus
{
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const SCHEDULED = 'scheduled';

    public const PAID = 'paid';

    public const RECONCILED = 'reconciled';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::SCHEDULED,
            self::PAID,
            self::RECONCILED,
            self::CANCELLED,
        ];
    }

    /** @return list<string> */
    public static function encumbering(): array
    {
        return [
            self::PENDING_APPROVAL,
            self::APPROVED,
            self::SCHEDULED,
            self::PAID,
            self::RECONCILED,
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
            self::PENDING_APPROVAL => __('Pending approval'),
            self::APPROVED => __('Approved'),
            self::SCHEDULED => __('Scheduled'),
            self::PAID => __('Paid'),
            self::RECONCILED => __('Reconciled'),
            self::CANCELLED => __('Cancelled'),
            default => $status,
        };
    }
}
