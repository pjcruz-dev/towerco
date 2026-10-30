<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Cash must be requested at least three calendar days before it is needed.
 */
final class EApprovalFundsNeededBy
{
    public const LEAD_DAYS = 3;

    public static function error(?string $value, ?DateTimeInterface $today = null): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $needed = DateTimeImmutable::createFromFormat('Y-m-d', substr($raw, 0, 10));
        if ($needed === false) {
            return __('Funds needed by must be a date.');
        }

        $start = $today !== null
            ? DateTimeImmutable::createFromInterface($today)->setTime(0, 0)
            : new DateTimeImmutable('today');
        $earliest = $start->modify('+'.self::LEAD_DAYS.' days');

        if ($needed->setTime(0, 0) < $earliest) {
            return __('Funds needed by must be at least :days days from today.', ['days' => self::LEAD_DAYS]);
        }

        return null;
    }
}
