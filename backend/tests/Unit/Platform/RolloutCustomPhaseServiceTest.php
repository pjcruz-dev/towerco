<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Models\RolloutCustomPhase;
use App\Modules\Platform\Services\RolloutCustomPhaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RolloutCustomPhaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_and_present_custom_phase(): void
    {
        $service = new RolloutCustomPhaseService();

        $phase = $service->create([
            'phase_key' => 'lgu_clearance',
            'label' => 'LGU Clearance',
            'description' => 'Mayor / LGU approval gate',
            'owner_role' => 'saq',
            'default_anchor' => 'tssr_approved',
            'default_working_day_start' => 10,
            'default_working_day_end' => 14,
            'default_gate' => 'Mayor approval',
            'counts_toward_sla' => false,
            'applicable_templates' => ['bts', 'rtb'],
        ]);

        $presented = $service->present($phase);

        $this->assertSame('lgu_clearance', $presented['phase_key']);
        $this->assertFalse($presented['counts_toward_sla']);
        $this->assertSame(['bts', 'rtb'], $presented['applicable_templates']);
    }

    public function test_to_timeline_phase_marks_custom_metadata(): void
    {
        $service = new RolloutCustomPhaseService();

        /** @var RolloutCustomPhase $phase */
        $phase = RolloutCustomPhase::query()->create([
            'phase_key' => 'finance_capex',
            'label' => 'Finance Capex Release',
            'default_anchor' => 'tssr_approved',
            'default_working_day_start' => 1,
            'default_working_day_end' => 3,
            'counts_toward_sla' => true,
            'applicable_templates' => ['bts'],
            'is_active' => true,
        ]);

        $row = $service->toTimelinePhase($phase);

        $this->assertTrue($row['is_custom']);
        $this->assertSame($phase->id, $row['catalog_phase_id']);
        $this->assertSame('finance_capex', $row['phase_key']);
    }
}
