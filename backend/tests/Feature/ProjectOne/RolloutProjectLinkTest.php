<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\Rollout\Models\TenantRolloutPlaybookConfig;
use App\Modules\Rollout\Data\RolloutPlaybookV2Definition;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Sites\Models\Site;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RolloutProjectLinkTest extends TestCase
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
        $this->seedPlaybook();
    }

    public function test_create_rollout_with_project_id_links_program(): void
    {
        [$project] = $this->seedProjectAndSite();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Linked ring',
                'project_id' => $project->id,
            ]);

        $response->assertCreated();

        tenancy()->initialize($this->testTenant);
        $rollout = RolloutProgram::query()->findOrFail($response->json('data.id'));
        $this->assertSame($project->id, $rollout->project_id);
        tenancy()->end();
    }

    public function test_create_rollout_persists_site_profile_fields(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/rollouts', [
                'mno' => 'globe',
                'project_type' => 'bts',
                'search_ring_name' => 'Profile ring',
                'region' => 'ncr',
                'full_address' => '123 Rizal Ave, Manila',
                'latitude' => 14.5995,
                'longitude' => 120.9842,
            ]);

        $response->assertCreated();

        tenancy()->initialize($this->testTenant);
        $rollout = RolloutProgram::query()->with('site')->findOrFail($response->json('data.id'));
        $this->assertNotNull($rollout->site);
        $this->assertSame('123 Rizal Ave, Manila', $rollout->site->full_address);
        $this->assertEqualsWithDelta(14.5995, (float) $rollout->site->latitude, 0.0001);
        $this->assertEqualsWithDelta(120.9842, (float) $rollout->site->longitude, 0.0001);
        tenancy()->end();
    }

    public function test_patch_rollout_reassigns_project(): void
    {
        [$project] = $this->seedProjectAndSite();

        tenancy()->initialize($this->testTenant);
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-LINK-TEST',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'sla_working_days' => 115,
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollouts/'.$rollout->id, [
                'project_id' => $project->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.project.id', $project->id);
    }

    public function test_site_mismatch_rejects_project_link(): void
    {
        tenancy()->initialize($this->testTenant);

        $siteA = Site::query()->create([
            'site_code' => 'A-001',
            'name' => 'Site A',
            'type' => 'macro',
            'status' => 'active',
        ]);
        $siteB = Site::query()->create([
            'site_code' => 'B-001',
            'name' => 'Site B',
            'type' => 'macro',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'name' => 'Project on A',
            'site_id' => $siteA->id,
            'status' => 'active',
        ]);

        $rollout = RolloutProgram::query()->create([
            'playbook_version' => '2.0.0',
            'rollout_ref' => 'RP-MISMATCH',
            'mno' => 'globe',
            'project_type' => 'bts',
            'status' => 'saq',
            'site_id' => $siteB->id,
            'sla_working_days' => 115,
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/rollouts/'.$rollout->id, [
                'project_id' => $project->id,
            ]);

        $response->assertStatus(422);
    }

    /**
     * @return array{0: Project}
     */
    private function seedProjectAndSite(): array
    {
        tenancy()->initialize($this->testTenant);

        $site = Site::query()->create([
            'site_code' => 'PRJ-001',
            'name' => 'Project Site',
            'type' => 'macro',
            'status' => 'active',
        ]);

        $project = Project::query()->create([
            'name' => 'Linked Project',
            'site_id' => $site->id,
            'status' => 'active',
        ]);

        tenancy()->end();

        return [$project];
    }

    private function seedPlaybook(): void
    {
        tenancy()->initialize($this->testTenant);

        TenantRolloutPlaybookConfig::query()->create([
            'assigned_version' => '2.0.0',
            'latest_platform_version' => '2.0.0',
            'playbook_snapshot' => RolloutPlaybookV2Definition::payload(),
            'day_overrides' => [],
            'assigned_at' => now(),
        ]);

        tenancy()->end();
    }
}
