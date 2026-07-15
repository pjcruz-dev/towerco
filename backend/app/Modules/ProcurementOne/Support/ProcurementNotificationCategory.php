<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementNotificationCategory
{
    public static function forType(string $type): string
    {
        return match ($type) {
            'rfq_bid_received', 'rfq_bid_revised' => 'action_required',
            'rfq_bidding_closed' => 'action_required',
            default => 'update',
        };
    }

    public static function hrefFor(?string $rfqId): string
    {
        return $rfqId !== null && $rfqId !== ''
            ? '/procurement/rfqs/'.$rfqId
            : '/procurement/rfqs';
    }
}
