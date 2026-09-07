<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynAutomaticIdService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DynAutomaticIdServiceTest extends TestCase
{
    #[Test]
    public function it_formats_prefix_year_and_sequence_like_metacoresoft(): void
    {
        $service = new DynAutomaticIdService;
        $now = Carbon::create(2026, 9, 7, 12, 0, 0);

        $this->assertSame(
            '2026-0001',
            $service->applyFormat('Y-####', 1, $now),
        );
        $this->assertSame(
            '2026-09-0007',
            $service->applyFormat('YYYY-MM-####', 7, $now),
        );
        $this->assertSame(
            '2026',
            $service->periodKeyFromFormat('Y-####', $now),
        );
        $this->assertSame(
            '2026-09',
            $service->periodKeyFromFormat('YYYY-MM-####', $now),
        );
        $this->assertSame(
            '',
            $service->periodKeyFromFormat('####', $now),
        );
    }
}
