<?php

declare(strict_types=1);

namespace Tests\Feature\Documents;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Services\DocumentSiteReviewFormProvisionerService;
use App\Modules\Documents\Support\DocumentApprovalStatus;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class DocumentSiteBinderEApprovalIntegrationTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.tenant_modules.enabled' => [
                'core', 'team_access', 'e_approval', 'sites', 'documents', 'project_one',
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
            'site_code' => 'ATC-BINDER-EA',
            'name' => 'Binder E-Approval Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_site_binder_request_approval_uses_site_document_review_form_and_completes_workflow(): void
    {
        tenancy()->initialize($this->testTenant);
        $actor = TenantUser::query()->findOrFail($this->testTenantAdmin->id);
        $form = app(DocumentSiteReviewFormProvisionerService::class)->ensure($actor);
        tenancy()->end();

        $workspace = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/sites/{$this->site->id}/documents/workspace")
            ->assertOk();

        $uploadNode = collect($workspace->json('data.nodes'))->firstWhere('node_key', 'saq_phase_1');
        $this->assertNotNull($uploadNode);

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/sites/{$this->site->id}/documents/files", [
                'site_node_id' => $uploadNode['id'],
                'file' => UploadedFile::fake()->create('saq-phase1.pdf', 50, 'application/pdf'),
                'title' => 'SAQ Phase 1 Package',
            ])
            ->assertCreated();

        $documentId = $upload->json('data.id');

        $approval = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/documents/files/{$documentId}/request-approval", [
                'form_id' => $form->id,
            ])
            ->assertCreated();

        $submissionId = $approval->json('data.submission.id');
        $this->assertNotEmpty($submissionId);
        $this->assertSame('pending', $approval->json('data.document.approval_status'));

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}")
            ->assertOk();

        $valuesByName = collect($detail->json('data.values'))->keyBy('field_name');
        $this->assertSame('SAQ Phase 1 Package', $valuesByName->get('document_title')['value'] ?? null);
        $this->assertSame('ATC-BINDER-EA', $valuesByName->get('site_code')['value'] ?? null);
        $this->assertSame('SAQ Phase 1', $valuesByName->get('binder_folder')['value'] ?? null);

        $approvalId = $detail->json('data.viewer_pending_approval_id');
        $this->assertNotEmpty($approvalId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $documentDetail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/documents/files/{$documentId}")
            ->assertOk();

        $this->assertSame('approved', $documentDetail->json('data.approval_status'));

        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->find($submissionId);
        $document = Document::query()->find($documentId);
        $this->assertNotNull($submission);
        $this->assertSame('approved', $submission->status);
        $this->assertNotNull($document);
        $this->assertSame(DocumentApprovalStatus::APPROVED, $document->approval_status);
        $this->assertSame(DocumentStatus::FINAL, $document->status);
        tenancy()->end();
    }
}
