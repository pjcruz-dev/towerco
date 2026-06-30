<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class WorkspaceSearchTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'sites',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'WS-SEARCH-001',
            'name' => 'Workspace Search Test Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_workspace_search_returns_matching_entities(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=WS-SEARCH');

        $response->assertOk()
            ->assertJsonFragment([
                'module' => 'sites',
                'entity_type' => 'site',
                'id' => (string) $this->site->id,
                'title' => 'WS-SEARCH-001 · Workspace Search Test Site',
                'href' => '/sites/'.$this->site->id,
            ]);
    }

    public function test_workspace_search_includes_controlled_documents(): void
    {
        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'documents',
            ],
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        \App\Modules\Documents\Models\ControlledDocument::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'document_code' => 'WS-CD-SEARCH-001',
            'title' => 'Quality Manual',
            'department' => 'QMS',
            'current_revision' => 1,
            'status' => \App\Modules\Documents\Support\ControlledDocumentStatus::PUBLISHED,
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=WS-CD-SEARCH')
            ->assertOk()
            ->assertJsonFragment([
                'module' => 'documents',
                'entity_type' => 'controlled_document',
                'title' => 'WS-CD-SEARCH-001',
            ]);
    }

    public function test_workspace_search_returns_empty_for_short_query(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=a')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_workspace_search_requires_permission(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions([]);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=WS-SEARCH')
            ->assertForbidden();
    }
}
