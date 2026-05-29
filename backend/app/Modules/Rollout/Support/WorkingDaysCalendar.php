<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * SLA and timeline math uses working days only (Mon–Fri).
 * Public holidays can be injected per tenant region in a later phase.
 */
final class WorkingDaysCalendar
{
    /** @var list<string> Y-m-d */
    private array $holidays;

    /**
     * @param  list<string>  $holidays
     */
    public function __construct(array $holidays = [])
    {
        $this->holidays = $holidays;
    }

    public function isWorkingDay(CarbonInterface $date): bool
    {
        if ($date->isWeekend()) {
            return false;
        }

        return ! in_array($date->toDateString(), $this->holidays, true);
    }

    public function addWorkingDays(CarbonInterface $start, int $workingDays): Carbon
    {
        $cursor = Carbon::parse($start->toDateString())->startOfDay();
        if ($workingDays === 0) {
            return $cursor;
        }

        $added = 0;
        $direction = $workingDays > 0 ? 1 : -1;
        $remaining = abs($workingDays);

        while ($added < $remaining) {
            $cursor->addDays($direction);
            if ($this->isWorkingDay($cursor)) {
                $added++;
            }
        }

        return $cursor;
    }

    public function workingDaysBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = Carbon::parse($from->toDateString());
        $end = Carbon::parse($to->toDateString());

        if ($start->equalTo($end)) {
            return 0;
        }

        $forward = $start->lessThan($end);
        $cursor = $forward ? $start->copy() : $end->copy();
        $limit = $forward ? $end : $start;
        $count = 0;

        while ($cursor->lessThan($limit)) {
            $cursor->addDay();
            if ($this->isWorkingDay($cursor)) {
                $count++;
            }
        }

        return $forward ? $count : -$count;
    }

    public function nextWorkingDay(CarbonInterface $date): Carbon
    {
        $cursor = Carbon::parse($date->toDateString());

        do {
            $cursor->addDay();
        } while (! $this->isWorkingDay($cursor));

        return $cursor;
    }
}
