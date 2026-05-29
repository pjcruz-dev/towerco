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

final class RolloutHappyPathApiTest extends TestCase
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

    public function test_bts_rollout_happy_path_through_rfi(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Happy path ring',
                'endorsement_date' => '2026-04-01',
            ]);

        $create->assertCreated();
        $rolloutId = $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rolloutId.'/tssr-approved', [
                'tssr_approved_date' => '2026-04-28',
            ])
            ->assertOk()
            ->assertJsonPath('data.tssr_approved_date', '2026-04-28');

        $candidateIds = [];
        foreach (['Candidate A', 'Candidate B', 'Candidate C'] as $index => $label) {
            $response = $this->actingAsTenantAdmin()
                ->withHeaders($this->tenantApiHeaders())
                ->postJson('/api/v1/project-one/rollouts/'.$rolloutId.'/candidates', [
                    'label' => $label,
                    'latitude' => 14.676 + ($index * 0.001),
                    'longitude' => 121.0437 + ($index * 0.001),
                ]);

            $response->assertCreated();
            $candidateIds[] = $response->json('data.id');
        }

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/candidates/'.$candidateIds[0].'/select')
            ->assertOk()
            ->assertJsonPath('data.tco_site_id', fn ($value) => is_string($value) && $value !== '');

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/rollouts/'.$rolloutId)
            ->assertOk();

        $huntingPhase = collect($detail->json('data.timeline_phases'))
            ->firstWhere('phase_key', 'site_hunting');

        $this->assertNotNull($huntingPhase);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollout-phases/'.$huntingPhase['id'].'/gate', [
                'gate_status' => 'passed',
            ])
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts/'.$rolloutId.'/rfi-recorded', [
                'actual_rfi_date' => '2026-05-15',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.actual_rfi_date', '2026-05-15');

        tenancy()->initialize($this->testTenant);
        $rollout = RolloutProgram::query()->findOrFail($rolloutId);
        $this->assertSame('completed', $rollout->status);
        $this->assertNotNull($rollout->tco_site_id);
        tenancy()->end();
    }
}
