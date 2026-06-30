<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\DocumentUploadIntent;
use App\Modules\Documents\Services\DocumentPresignedUploadService;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentPresignedUploadTest extends TestCase
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
            'toweros.documents.presigned_upload_enabled' => true,
            'toweros.documents.presigned_upload_min_kb' => 10240,
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'PRE-001',
            'name' => 'Presign Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_upload_capabilities_reflect_local_disk(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/upload-capabilities');

        $response->assertOk();
        $response->assertJsonPath('data.direct_upload_enabled', false);
        $response->assertJsonPath('data.multipart_fallback', true);
        $this->assertContains('dwg', $response->json('data.cad_extensions'));
    }

    public function test_presign_rejected_when_storage_is_not_s3(): void
    {
        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $uploadNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'saq_phase_1');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/sites/{$this->site->id}/documents/files/presign", [
                'site_node_id' => $uploadNode['id'],
                'filename' => 'large-layout.dwg',
                'mime_type' => 'application/octet-stream',
                'size_bytes' => 15 * 1024 * 1024,
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['upload']);
    }

    public function test_complete_presigned_upload_creates_document(): void
    {
        tenancy()->initialize($this->testTenant);
        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($this->site);
        $node = $workspace->nodes()->where('node_key', 'saq_phase_1')->firstOrFail();

        $documentId = (string) Str::uuid();
        $storedPath = $this->testTenant->id.'/documents/'.$this->site->id.'/'.$documentId.'/v1/'.Str::uuid().'.dwg';
        $content = str_repeat('A', 2048);
        Storage::disk('tenant_files')->put($storedPath, $content);

        $token = Str::random(48);
        DocumentUploadIntent::query()->create([
            'id' => (string) Str::uuid(),
            'upload_token' => $token,
            'site_id' => $this->site->id,
            'site_node_id' => $node->id,
            'document_id' => $documentId,
            'stored_path' => $storedPath,
            'original_filename' => 'site-plan.dwg',
            'mime_type' => 'application/octet-stream',
            'size_bytes' => strlen($content),
            'uploaded_by_id' => $this->testTenantAdmin->id,
            'expires_at' => now()->addMinutes(15),
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/sites/{$this->site->id}/documents/files/complete", [
                'upload_token' => $token,
            ]);

        $response->assertCreated();
        $this->assertSame('site-plan.dwg', $response->json('data.title'));
        $this->assertSame($documentId, $response->json('data.id'));

        tenancy()->initialize($this->testTenant);
        $this->assertNotNull(
            DocumentUploadIntent::query()->where('upload_token', $token)->value('consumed_at'),
        );
        tenancy()->end();
    }

    public function test_presigned_upload_service_capabilities_include_cad_extensions(): void
    {
        tenancy()->initialize($this->testTenant);
        $caps = app(DocumentPresignedUploadService::class)->capabilities();
        tenancy()->end();

        $this->assertFalse($caps['direct_upload_enabled']);
        $this->assertContains('dxf', $caps['cad_extensions']);
    }
}
