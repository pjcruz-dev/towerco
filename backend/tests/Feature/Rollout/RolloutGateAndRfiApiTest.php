<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutGateAndRfiApiTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_patch_gate_status_updates_phase_and_returns_rollout_detail(): void
    {
        [$rollout, $phase] = $this->seedRolloutWithPhase();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollout-phases/'.$phase->id.'/gate', [
                'gate_status' => 'passed',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $rollout->id)
            ->assertJsonPath('data.timeline_phases.0.gate_status', 'passed')
            ->assertJsonPath('data.timeline_phases.0.phase_progress', 'completed');
    }

    public function test_post_rfi_recorded_completes_rollout_with_variance(): void
    {
        [$rollout] = $this->seedRolloutWithPhase();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rollout->id.'/rfi-recorded', [
                'actual_rfi_date' => '2026-05-15',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actual_rfi_date', '2026-05-15');

        $this->assertIsInt($response->json('data.sla_variance_working_days'));
    }

    /**
     * @return array{0: RolloutProgram, 1: RolloutTimelinePhase}
     */
    private function seedRolloutWithPhase(): array
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-TEST-001',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 120,
        ]);

        /** @var RolloutTimelinePhase $phase */
        $phase = RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $rollout->id,
            'phase_key' => 'phase_1',
            'label' => 'Phase 1',
            'owner_role' => 'pmo',
            'anchor' => 'tssr_approved',
            'working_day_start' => 1,
            'working_day_end' => 10,
            'gate_status' => 'pending',
            'gate_label' => 'TSSR approved',
            'sort_order' => 1,
        ]);

        tenancy()->end();

        return [$rollout, $phase];
    }
}
