<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementPaymentBatchStatus
{
    public const DRAFT = 'draft';

    public const SCHEDULED = 'scheduled';

    public const EXPORTED = 'exported';

    public const RECONCILED = 'reconciled';

    public const CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::DRAFT,
            self::SCHEDULED,
            self::EXPORTED,
            self::RECONCILED,
            self::CANCELLED,
        ];
    }

    public static function label(string $status): string
    {
        return match ($status) {
            self::DRAFT => __('Draft'),
            self::SCHEDULED => __('Scheduled'),
            self::EXPORTED => __('Exported'),
            self::RECONCILED => __('Reconciled'),
            self::CANCELLED => __('Cancelled'),
            default => $status,
        };
    }
}
