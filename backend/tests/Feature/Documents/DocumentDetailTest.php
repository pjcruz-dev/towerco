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

final class DocumentDetailTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'project_one', 'sites', 'documents',
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
            'site_code' => 'DET-001',
            'name' => 'Detail Test Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_document_detail_includes_versions_and_activity(): void
    {
        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $uploadNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'saq_phase_1');

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
            ]);

        $documentId = $upload->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/documents/files/{$documentId}/versions", [
                'file' => UploadedFile::fake()->create('contract-v2.pdf', 120, 'application/pdf'),
            ]);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/documents/files/{$documentId}");

        $detail->assertOk();
        $detail->assertJsonPath('data.version', 2);
        $detail->assertJsonStructure([
            'data' => [
                'download_url',
                'versions' => [['version', 'original_filename', 'size_bytes']],
                'activities' => [['id', 'event', 'at']],
            ],
        ]);
        $this->assertCount(2, $detail->json('data.versions'));
        $this->assertNotEmpty($detail->json('data.activities'));
    }
}
