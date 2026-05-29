<?php

declare(strict_types=1);

namespace Tests\Feature\ProjectOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProjectOne\Models\Milestone;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\Sites\Models\Site;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProjectCrudApiTest extends TestCase
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

    public function test_create_and_show_project_with_milestones_payload(): void
    {
        tenancy()->initialize($this->testTenant);

        $site = Site::query()->create([
            'site_code' => 'QC-001',
            'name' => 'Quezon Site',
            'type' => 'macro',
            'status' => 'active',
        ]);

        tenancy()->end();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/project-one/projects', [
                'name' => 'Test Integration Project',
                'site_id' => $site->id,
                'status' => 'planning',
                'start_date' => '2026-04-01',
                'end_date' => '2026-10-01',
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.name', 'Test Integration Project')
            ->assertJsonPath('data.site.site_code', 'QC-001');

        $projectId = $create->json('data.id');

        tenancy()->initialize($this->testTenant);
        Milestone::query()->create([
            'project_id' => $projectId,
            'name' => 'Permit approved',
            'status' => 'pending',
            'order_index' => 1,
        ]);
        tenancy()->end();

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/project-one/projects/'.$projectId);

        $show->assertOk()
            ->assertJsonPath('data.name', 'Test Integration Project')
            ->assertJsonCount(1, 'data.milestones')
            ->assertJsonPath('data.rollouts', []);
    }

    public function test_patch_project_updates_fields(): void
    {
        tenancy()->initialize($this->testTenant);

        /** @var Project $project */
        $project = Project::query()->create([
            'name' => 'Before rename',
            'status' => 'planning',
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/project-one/projects/'.$project->id, [
                'name' => 'After rename',
                'status' => 'active',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'After rename')
            ->assertJsonPath('data.status', 'active');
    }
}
