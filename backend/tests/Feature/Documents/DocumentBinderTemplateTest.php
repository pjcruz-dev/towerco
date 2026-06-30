<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Data\DocumentBinderTemplateDefaults;
use App\Modules\Documents\Models\DocumentBinderTemplate;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Sites\Models\Site;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentBinderTemplateTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'sites', 'documents',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_update_binder_template_persists_custom_tree(): void
    {
        $tree = DocumentBinderTemplateDefaults::tree();
        $tree[0]['label'] = 'Custom SAQ Binder';

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/documents/binder-template', ['tree' => $tree]);

        $response->assertOk()
            ->assertJsonPath('data.source', 'tenant_custom')
            ->assertJsonPath('data.tree.0.label', 'Custom SAQ Binder');
    }

    public function test_new_site_workspace_uses_custom_template(): void
    {
        $tree = DocumentBinderTemplateDefaults::tree();
        $tree[1]['children'][0]['label'] = 'Custom Drawings';

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/documents/binder-template', ['tree' => $tree])
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $site = Site::query()->create([
            'site_code' => 'TPL-001',
            'name' => 'Template Site',
            'status' => 'active',
        ]);
        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($site);
        $drawings = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_key', 'drawings')
            ->first();
        tenancy()->end();

        $this->assertNotNull($drawings);
        $this->assertSame('Custom Drawings', $drawings->label);
    }

    public function test_reset_binder_template_restores_platform_default(): void
    {
        $tree = DocumentBinderTemplateDefaults::tree();
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/documents/binder-template', ['tree' => $tree])
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/documents/binder-template/reset')
            ->assertOk()
            ->assertJsonPath('data.source', 'platform_default');

        tenancy()->initialize($this->testTenant);
        $this->assertSame(0, DocumentBinderTemplate::query()->count());
        tenancy()->end();
    }
}
