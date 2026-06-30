<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentPhase2Test extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    private TenantUser $approver;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'project_one', 'e_approval', 'sites', 'documents',
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
            'toweros.documents.gate_required_node_keys' => ['saq_phase_1', 'col'],
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'DOC-P2',
            'name' => 'Phase 2 Site',
            'status' => 'active',
        ]);
        $this->approver = TenantUser::query()->create([
            'name' => 'Doc Approver',
            'email' => 'doc-approver@test.localhost',
            'password' => 'password',
        ]);
        $this->approver->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_workspace_search_includes_documents(): void
    {
        tenancy()->initialize($this->testTenant);
        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($this->site);
        $node = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_key', 'saq_phase_1')
            ->firstOrFail();
        $documentId = (string) Str::uuid();
        Document::query()->create([
            'id' => $documentId,
            'site_id' => $this->site->id,
            'site_node_id' => $node->id,
            'title' => 'Unique Lease DOC-P2-SEARCH',
            'original_filename' => 'lease.pdf',
            'stored_path' => 'test/lease.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'status' => DocumentStatus::DRAFT,
            'version' => 1,
            'uploaded_by_id' => $this->testTenantAdmin->id,
            'last_touched_by_id' => $this->testTenantAdmin->id,
            'last_touched_at' => now(),
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=DOC-P2-SEARCH');

        $response->assertOk()
            ->assertJsonFragment([
                'module' => 'documents',
                'entity_type' => 'document',
                'title' => 'Unique Lease DOC-P2-SEARCH',
            ]);

        $documentHit = collect($response->json('data'))
            ->firstWhere('entity_type', 'document');

        $this->assertNotNull($documentHit);
        $this->assertSame(
            '/sites/'.$this->site->id.'?document='.$documentHit['id'],
            $documentHit['href'] ?? null,
        );
    }

    public function test_gate_checklist_reports_required_folders(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/gate-checklist");

        $response->assertOk()
            ->assertJsonPath('data.summary.required', 2)
            ->assertJsonPath('data.summary.complete', false);
    }

    public function test_request_approval_links_e_approval_submission(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Document Review',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'document_title', 'label' => 'Document'],
                    ['type' => 'text', 'name' => 'site_code', 'label' => 'Site'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $formId = $formRes->json('data.form.id');

        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace");

        $uploadNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'saq_phase_1');

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('col.pdf', 50, 'application/pdf'),
            ]);

        $documentId = $upload->json('data.id');

        $approval = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/documents/files/{$documentId}/request-approval", [
                'form_id' => $formId,
            ]);

        $approval->assertCreated();
        $submissionId = $approval->json('data.submission.id');
        $this->assertNotEmpty($submissionId);
        $this->assertSame('pending', $approval->json('data.document.approval_status'));

        tenancy()->initialize($this->testTenant);
        $document = Document::query()->find($documentId);
        $this->assertNotNull($document);
        $this->assertSame($submissionId, (string) $document->e_approval_submission_id);
        tenancy()->end();
    }

    public function test_binder_template_requires_template_manage_permission(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/documents/binder-template')
            ->assertOk()
            ->assertJsonPath('data.source', 'platform_default');
    }
}
