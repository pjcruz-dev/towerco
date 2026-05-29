<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RecalculateRolloutSlasCommandTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();
    }

    public function test_command_recalculates_target_rfi_after_regional_holiday_is_added(): void
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-RECALC-001',
            'mno' => 'glo',
            'project_type' => 'bts',
            'region' => 'ncr',
            'status' => 'permitting',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 5,
            'target_rfi_working_date' => '2026-05-05',
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $rollout->id,
            'phase_key' => 'phase_1',
            'label' => 'Phase 1',
            'anchor' => 'tssr_approved',
            'working_day_start' => 1,
            'working_day_end' => 5,
            'target_end_date' => '2026-05-05',
        ]);

        TenantPublicHoliday::query()->create([
            'holiday_date' => '2026-05-01',
            'name' => 'Labor Day',
            'region' => null,
            'calendar_year' => 2026,
        ]);

        tenancy()->end();

        $this->artisan('tenants:recalculate-rollout-slas', [
            '--tenant' => $this->testTenant->id,
        ])->assertSuccessful();

        tenancy()->initialize($this->testTenant);
        $rollout->refresh();
        $this->assertSame('2026-05-06', $rollout->target_rfi_working_date?->toDateString());
        tenancy()->end();
    }
}
