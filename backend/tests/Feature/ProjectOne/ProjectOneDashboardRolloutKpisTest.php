<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Sites\Models\Site;
use Carbon\Carbon;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProjectOneDashboardRolloutKpisTest extends TestCase
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
        $this->seedDashboardFixtures();
    }

    public function test_dashboard_includes_rollout_kpis_map_pins_and_saq_metrics(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.rollouts.active_rollouts', 1)
            ->assertJsonPath('data.rollouts.pending_gates', 1)
            ->assertJsonPath('data.rollouts.open_saq_programs', 1)
            ->assertJsonStructure([
                'data' => [
                    'map_pins' => [
                        ['type', 'id', 'lat', 'lng', 'label'],
                    ],
                    'kpis',
                ],
            ]);

        $kpiKeys = collect($response->json('data.kpis'))->pluck('key')->all();
        $this->assertContains('rollout_pending_gates', $kpiKeys);
        $this->assertContains('rollout_open_saq', $kpiKeys);
        $this->assertContains('rollout_sla_risk', $kpiKeys);

        $pins = $response->json('data.map_pins');
        $this->assertNotEmpty($pins);
        $this->assertTrue(collect($pins)->contains(static fn (array $pin): bool => $pin['type'] === 'candidate'));
    }

    public function test_rollout_map_endpoint_returns_geojson(): void
    {
        tenancy()->initialize($this->testTenant);
        $rollout = RolloutProgram::query()->where('rollout_ref', 'RP-DASH-MAP')->firstOrFail();
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rollout->id.'/map');

        $response->assertOk()
            ->assertJsonPath('data.type', 'FeatureCollection')
            ->assertJsonPath('data.properties.rollout_ref', 'RP-DASH-MAP')
            ->assertJsonCount(1, 'data.features');
    }

    private function seedDashboardFixtures(): void
    {
        tenancy()->initialize($this->testTenant);

        Site::query()->create([
            'site_code' => 'MAP-001',
            'name' => 'Map Site',
            'type' => 'macro',
            'status' => 'active',
            'latitude' => 14.676,
            'longitude' => 121.0437,
        ]);

        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-DASH-MAP',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'search_ring_name' => 'QC Ring',
            'region' => 'ncr',
            'sla_working_days' => 115,
            'target_rfi_working_date' => Carbon::today()->addDays(5),
        ]);

        SiteCandidate::query()->create([
            'rollout_program_id' => $rollout->id,
            'candidate_number' => 1,
            'status' => 'scouted',
            'label' => 'Candidate A',
            'latitude' => 14.677,
            'longitude' => 121.044,
        ]);

        RolloutTimelinePhase::query()->create([
            'rollout_program_id' => $rollout->id,
            'phase_key' => 'site_hunting',
            'label' => 'Site Hunting',
            'owner_role' => 'saq',
            'anchor' => 'endorsement',
            'working_day_start' => 1,
            'working_day_end' => 8,
            'gate_status' => 'pending',
            'gate_label' => '≥3 candidates',
            'sort_order' => 1,
        ]);

        tenancy()->end();
    }
}
