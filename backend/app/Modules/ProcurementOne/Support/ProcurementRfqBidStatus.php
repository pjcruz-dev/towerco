<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementRfqBidStatus
{
    public const DRAFT = 'draft';

    public const SUBMITTED = 'submitted';

    public const WITHDRAWN = 'withdrawn';

    public const AWARDED = 'awarded';

    public const REJECTED = 'rejected';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SUBMITTED,
            self::WITHDRAWN,
            self::AWARDED,
            self::REJECTED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::SUBMITTED => __('Submitted'),
            self::WITHDRAWN => __('Withdrawn'),
            self::AWARDED => __('Awarded'),
            self::REJECTED => __('Rejected'),
            default => $status,
        };
    }
}
