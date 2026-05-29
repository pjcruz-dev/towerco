<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Rollout\Support\WorkingDaysCalendar;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

final class WorkingDaysCalendarTest extends TestCase
{
    public function test_weekends_are_not_working_days(): void
    {
        $calendar = new WorkingDaysCalendar();

        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-05-16'))); // Saturday
        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-05-17'))); // Sunday
        $this->assertTrue($calendar->isWorkingDay(Carbon::parse('2026-05-18'))); // Monday
    }

    public function test_public_holidays_are_excluded(): void
    {
        $calendar = new WorkingDaysCalendar(['2026-05-01']);

        $this->assertFalse($calendar->isWorkingDay(Carbon::parse('2026-05-01'))); // Labor Day (Friday)
    }

    public function test_add_working_days_skips_weekends_and_holidays(): void
    {
        $calendar = new WorkingDaysCalendar(['2026-05-01']);

        $result = $calendar->addWorkingDays(Carbon::parse('2026-04-28'), 5);

        $this->assertSame('2026-05-06', $result->toDateString());
    }

    public function test_working_days_between_counts_signed_values(): void
    {
        $calendar = new WorkingDaysCalendar();

        $forward = $calendar->workingDaysBetween(Carbon::parse('2026-05-18'), Carbon::parse('2026-05-22'));
        $backward = $calendar->workingDaysBetween(Carbon::parse('2026-05-22'), Carbon::parse('2026-05-18'));

        $this->assertSame(4, $forward);
        $this->assertSame(-4, $backward);
    }
}
