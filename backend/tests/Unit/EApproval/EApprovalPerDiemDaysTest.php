<?php

declare(strict_types=1);

namespace Tests\Unit\EApproval;

use App\Modules\EApproval\Support\EApprovalPerDiemDays;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EApprovalPerDiemDaysTest extends TestCase
{
    #[Test]
    public function it_counts_the_dates_inside_one_activity(): void
    {
        $this->assertSame(3, EApprovalPerDiemDays::betweenTrips([
            ['from' => '2026-10-14', 'to' => '2026-10-18'],
        ]));
    }

    #[Test]
    public function it_excludes_the_outbound_and_return_activities(): void
    {
        $this->assertSame(3, EApprovalPerDiemDays::betweenTrips([
            ['from' => '2026-10-14', 'to' => '2026-10-14'],
            ['from' => '2026-10-15', 'to' => '2026-10-17'],
            ['from' => '2026-10-18', 'to' => '2026-10-18'],
        ]));
    }

    #[Test]
    public function it_returns_zero_when_the_trip_starts_and_ends_on_the_same_day(): void
    {
        $this->assertSame(0, EApprovalPerDiemDays::betweenTrips([
            ['from' => '2026-10-14', 'to' => '2026-10-14'],
        ]));
    }

    #[Test]
    public function it_counts_the_days_between_the_first_and_last_date_of_a_chain(): void
    {
        $this->assertSame(2, EApprovalPerDiemDays::betweenTrips([
            ['from' => '2026-10-14', 'to' => '2026-10-15'],
            ['from' => '2026-10-15', 'to' => '2026-10-16'],
            ['from' => '2026-10-16', 'to' => '2026-10-17'],
        ]));
    }

    #[Test]
    public function it_counts_each_per_diem_day_including_the_first_and_the_last(): void
    {
        $this->assertSame(1, EApprovalPerDiemDays::inclusiveDays('2026-10-14', '2026-10-14'));
        $this->assertSame(3, EApprovalPerDiemDays::inclusiveDays('2026-10-14', '2026-10-16'));
        $this->assertSame(5, EApprovalPerDiemDays::inclusiveDays('2026-10-14', '2026-10-18'));
        $this->assertNull(EApprovalPerDiemDays::inclusiveDays('2026-10-16', '2026-10-14'));
        $this->assertSame(1, EApprovalPerDiemDays::forDateValue('{"from":"2026-10-14","to":"2026-10-14"}'));
        $this->assertSame(3, EApprovalPerDiemDays::inclusiveDays('2026-10-14T08:30', '2026-10-16T17:00'));
        $this->assertSame(2, EApprovalPerDiemDays::lodgingNights('2026-10-14T08:30', '2026-10-16T17:00'));
        $this->assertSame(3, EApprovalPerDiemDays::forDateValue('{"from":"2026-10-14T08:30","to":"2026-10-16T17:00"}'));
    }

    #[Test]
    public function it_returns_null_when_no_activity_has_dates(): void
    {
        $this->assertNull(EApprovalPerDiemDays::betweenTrips([]));
        $this->assertNull(EApprovalPerDiemDays::betweenTrips([
            ['from' => '', 'to' => ''],
        ]));
    }
}
