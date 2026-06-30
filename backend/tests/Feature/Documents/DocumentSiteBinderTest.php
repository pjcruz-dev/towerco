<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentSiteBinderTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'project_one', 'e_approval', 'ticketing', 'sites', 'documents',
            ],
        ]);

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
        $this->testTenant->update(['plan_tier' => 'professional']);

        Storage::fake('tenant_files');
        config([
            'toweros.tenant_files.disk' => 'tenant_files',
            'toweros.documents.max_size_kb' => 51200,
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'AT-900',
            'name' => 'Binder Test Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_workspace_seeds_default_binder_and_uploads_file(): void
    {
        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $workspace->assertOk();
        $nodes = $workspace->json('data.nodes');
        $this->assertNotEmpty($nodes);

        $uploadNode = collect($nodes)->firstWhere('node_key', 'saq_phase_1');
        $this->assertNotNull($uploadNode);

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('lease.pdf', 100, 'application/pdf'),
            ]);

        $upload->assertCreated();
        $this->assertSame('lease.pdf', $upload->json('data.title'));
        $this->assertNotEmpty($upload->json('data.last_touched_by.name'));
    }

    public function test_add_lessor_creates_repeatable_folder(): void
    {
        $lessor = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/sites/{$this->site->id}/documents/lessors", [
                'lessor_name' => 'Maria Santos',
                'lessor_contact' => '09171234567',
            ]);

        $lessor->assertCreated();
        $this->assertStringContainsString('Maria Santos', (string) $lessor->json('data.instance.label'));
    }
}
