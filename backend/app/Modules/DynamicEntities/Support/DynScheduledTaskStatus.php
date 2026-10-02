<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Same status rules as the platform Schedules page.
 * Failed wins, then paused, then overdue when the next run is more than five minutes late.
 */
final class DynScheduledTaskStatus
{
    public const OVERDUE_GRACE_MINUTES = 5;

    public static function resolve(bool $isActive, mixed $nextRunAt, mixed $lastStatus, ?CarbonInterface $now = null): string
    {
        $now ??= Carbon::now();

        if ($lastStatus === 'failed') {
            return 'failed';
        }

        if (! $isActive) {
            return 'paused';
        }

        $next = self::moment($nextRunAt);
        if ($next !== null && $next->lt($now->copy()->subMinutes(self::OVERDUE_GRACE_MINUTES))) {
            return 'overdue';
        }

        return 'active';
    }

    private static function moment(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
