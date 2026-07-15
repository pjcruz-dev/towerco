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

final class DocumentCadUploadTest extends TestCase
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
            'site_code' => 'CAD-001',
            'name' => 'CAD Upload Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_multipart_upload_accepts_cad_dwg_with_octet_stream_mime(): void
    {
        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $uploadNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'saq_phase_1');
        $this->assertNotNull($uploadNode);

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('tower-layout.dwg', 256, 'application/octet-stream'),
            ]);

        $upload->assertCreated();
        $this->assertSame('tower-layout.dwg', $upload->json('data.title'));
        $this->assertSame('application/octet-stream', $upload->json('data.mime_type'));
    }

    public function test_multipart_upload_rejects_unknown_extension(): void
    {
        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $uploadNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'saq_phase_1');

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('malware.exe', 64, 'application/octet-stream'),
            ]);

        $upload->assertUnprocessable();
    }
}
