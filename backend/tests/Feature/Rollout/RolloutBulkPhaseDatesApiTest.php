<?php

declare(strict_types=1);

namespace Tests\Feature\Rollout;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\Support\Concerns\SeedsTenantRolloutPlaybook;
use Tests\TestCase;

final class RolloutBulkPhaseDatesApiTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;
    use SeedsTenantRolloutPlaybook;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->seedTenantRolloutPlaybook();
    }

    public function test_bulk_phase_dates_backfills_actual_end_date_and_gate_status(): void
    {
        [$rolloutA, $phaseA] = $this->seedRolloutWithPhase('site_hunting');
        [$rolloutB, $phaseB] = $this->seedRolloutWithPhase('site_hunting');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/bulk-phase-dates', [
                'rollout_ids' => [$rolloutA->id, $rolloutB->id],
                'backfill' => true,
                'mark_gate_passed' => true,
                'phases' => [
                    ['phase_key' => 'site_hunting', 'actual_date' => '2026-03-15'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.phases_applied', 2);

        tenancy()->initialize($this->testTenant);

        foreach ([$phaseA, $phaseB] as $phase) {
            $fresh = RolloutTimelinePhase::query()->findOrFail($phase->id);
            $this->assertSame('passed', $fresh->gate_status);
            $this->assertSame('2026-03-15', $fresh->actual_end_date?->toDateString());
        }

        tenancy()->end();
    }

    public function test_bulk_phase_dates_grid_applies_per_rollout_rows(): void
    {
        [$rolloutA, $phaseA] = $this->seedRolloutWithPhase('endorsement', 'RP-2026-GLO-EZVL');
        [$rolloutB, $phaseB] = $this->seedRolloutWithPhase('endorsement', 'RP-2026-GLO-OLMD');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/bulk-phase-dates-grid', [
                'backfill' => true,
                'rows' => [
                    [
                        'rollout_id' => $rolloutA->id,
                        'phases' => [['phase_key' => 'endorsement', 'actual_date' => '2026-02-10']],
                    ],
                    [
                        'rollout_id' => $rolloutB->id,
                        'phases' => [['phase_key' => 'endorsement', 'actual_date' => '2026-03-20']],
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 2)
            ->assertJsonPath('data.phases_applied', 2);

        tenancy()->initialize($this->testTenant);

        $freshA = RolloutTimelinePhase::query()->findOrFail($phaseA->id);
        $freshB = RolloutTimelinePhase::query()->findOrFail($phaseB->id);
        $this->assertSame('2026-02-10', $freshA->actual_end_date?->toDateString());
        $this->assertSame('2026-03-20', $freshB->actual_end_date?->toDateString());

        tenancy()->end();
    }

    public function test_bulk_phase_dates_skips_rollout_without_matching_phase(): void
    {
        [$rollout, $phase] = $this->seedRolloutWithPhase('site_hunting');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/bulk-phase-dates', [
                'rollout_ids' => [$rollout->id],
                'backfill' => true,
                'phases' => [
                    ['phase_key' => 'site_hunting', 'actual_date' => '2026-04-01'],
                    ['phase_key' => 'construction', 'actual_date' => '2026-08-01'],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.phases_applied', 1);

        tenancy()->initialize($this->testTenant);
        $fresh = RolloutTimelinePhase::query()->findOrFail($phase->id);
        $this->assertSame('2026-04-01', $fresh->actual_end_date?->toDateString());
        tenancy()->end();
    }

    /**
     * @return array{0: RolloutProgram, 1: RolloutTimelinePhase}
     */
    private function seedRolloutWithPhase(string $phaseKey, ?string $rolloutRef = null): array
    {
        tenancy()->initialize($this->testTenant);

        /** @var RolloutProgram $rollout */
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => $rolloutRef ?? 'RP-PHASE-'.uniqid('', true),
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);

        /** @var RolloutTimelinePhase $phase */
        $phase = RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $rollout->id,
            'phase_key' => $phaseKey,
            'label' => $phaseKey === 'endorsement' ? 'Endorsement & Planning' : 'Site Hunting',
            'owner_role' => $phaseKey === 'endorsement' ? 'bd_pmo' : 'saq',
            'anchor' => 'endorsement',
            'working_day_start' => 0,
            'working_day_end' => $phaseKey === 'endorsement' ? 0 : 8,
            'gate_status' => 'pending',
            'gate_label' => 'Gate',
            'sort_order' => 1,
        ]);

        tenancy()->end();

        return [$rollout, $phase];
    }
}
