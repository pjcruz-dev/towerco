<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Support\DynScheduledTaskStatus;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DynScheduledTaskStatusTest extends TestCase
{
    #[Test]
    public function overdue_matches_the_console_five_minute_rule(): void
    {
        $now = Carbon::parse('2026-10-02 09:00:00');

        $this->assertSame(
            'overdue',
            DynScheduledTaskStatus::resolve(true, '2026-10-02 08:50:00', 'ok', $now),
        );
        $this->assertSame(
            'active',
            DynScheduledTaskStatus::resolve(true, '2026-10-02 08:56:00', 'ok', $now),
        );
        $this->assertSame(
            'paused',
            DynScheduledTaskStatus::resolve(false, '2026-10-02 08:00:00', 'ok', $now),
        );
        $this->assertSame(
            'failed',
            DynScheduledTaskStatus::resolve(true, '2026-10-02 08:00:00', 'failed', $now),
        );
    }
}
