<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\Document;
use App\Modules\Documents\Models\DocumentSiteNode;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Documents\Support\DocumentStatus;
use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementContractTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

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
                'e_approval',
                'procurement_one',
                'finance_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_contract_spend_ceiling_blocks_po_and_tracks_commitment(): void
    {
        [$prId, $vendorId] = $this->bootstrapApprovedPrWithVendor();
        $this->createPurchaseOrderForm();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/procurement-one/settings', [
                'contract_spend_policy' => ['enabled' => true, 'mode' => 'block'],
            ])
            ->assertOk();

        $createContract = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/contracts', [
                'title' => 'Tower lease maintenance',
                'vendor_id' => $vendorId,
                'spend_ceiling' => 1500,
                'end_date' => now()->addYear()->format('Y-m-d'),
            ]);
        $createContract->assertCreated();
        $contractId = (string) $createContract->json('data.contract.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/contracts/{$contractId}/activate")
            ->assertOk()
            ->assertJsonPath('data.contract.status', ProcurementContractStatus::ACTIVE);

        $blocked = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/pos', [
                'pr_ids' => [$prId],
                'contract_id' => $contractId,
                'vendor_code' => 'VEND-CON',
                'vendor_name' => 'Contract Vendor',
                'lines' => [
                    ['description' => 'Over ceiling item', 'quantity' => 2, 'unit_price' => 1000],
                ],
            ]);
        $blocked->assertStatus(422);

        $po = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/pos', [
                'pr_ids' => [$prId],
                'contract_id' => $contractId,
                'vendor_code' => 'VEND-CON',
                'vendor_name' => 'Contract Vendor',
                'lines' => [
                    ['description' => 'Within ceiling item', 'quantity' => 1, 'unit_price' => 1200],
                ],
            ]);
        $po->assertCreated();
        $poId = (string) $po->json('data.id');
        $this->assertSame($contractId, (string) $po->json('data.contract_id'));
        $grandTotal = (float) $po->json('data.grand_total');

        tenancy()->initialize($this->testTenant);
        $contract = ProcurementContract::query()->find($contractId);
        $this->assertNotNull($contract);
        $this->assertSame($grandTotal, (float) $contract->committed_po_amount);
        $this->assertSame($contractId, (string) ProcurementPo::query()->find($poId)?->contract_id);
        tenancy()->end();

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/contracts/{$contractId}");
        $detail->assertOk();
        $this->assertEquals($grandTotal, (float) $detail->json('data.contract.live_committed_po_amount'));
        $this->assertEquals(round(1500 - $grandTotal, 2), (float) $detail->json('data.contract.available_spend'));
    }

    public function test_contract_end_date_syncs_primary_document_expiry(): void
    {
        tenancy()->initialize($this->testTenant);
        $vendorId = (string) ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-DOC',
            'company_name' => 'Document Vendor',
            'tax_id' => 'VEND-DOC',
            'category' => 'general',
            'schema_version' => 1,
            'contact_json' => [],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => 'accredited',
            'is_active' => true,
        ])->id;

        $site = Site::query()->create([
            'site_code' => 'CON-DOC',
            'name' => 'Contract Site',
            'status' => 'active',
        ]);
        $workspace = app(DocumentWorkspaceService::class)->ensureForSite($site);
        $node = DocumentSiteNode::query()
            ->where('workspace_id', $workspace->id)
            ->where('node_key', 'vendor_contracts')
            ->firstOrFail();

        $documentId = (string) Document::query()->create([
            'site_id' => (string) $site->id,
            'site_node_id' => (string) $node->id,
            'title' => 'Signed vendor agreement',
            'original_filename' => 'agreement.pdf',
            'stored_path' => 'test/agreement.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 100,
            'status' => DocumentStatus::FINAL,
            'version' => 1,
            'uploaded_by_id' => $this->testTenantAdmin->id,
            'last_touched_by_id' => $this->testTenantAdmin->id,
            'last_touched_at' => now(),
        ])->id;
        tenancy()->end();

        $endDate = now()->addMonths(6)->format('Y-m-d');

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/contracts', [
                'title' => 'Document-linked contract',
                'vendor_id' => $vendorId,
                'primary_document_id' => $documentId,
                'end_date' => $endDate,
            ]);
        $create->assertCreated();
        $contractId = (string) $create->json('data.contract.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/contracts/{$contractId}/activate")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $document = Document::query()->find($documentId);
        $this->assertNotNull($document?->expires_at);
        $this->assertSame($endDate, $document->expires_at->format('Y-m-d'));
        $this->assertSame($documentId, (string) ProcurementContract::query()->find($contractId)?->primary_document_id);
        tenancy()->end();

        $expiring = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/contracts/expiring?summary_only=1&within_days=365');
        $expiring->assertOk();
        $this->assertNotEmpty($expiring->json('data.rows'));
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function bootstrapApprovedPrWithVendor(): array
    {
        $this->createPurchaseRequisitionForm();
        tenancy()->initialize($this->testTenant);
        $vendorId = (string) ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-CON',
            'company_name' => 'Contract Vendor',
            'tax_id' => 'VEND-CON',
            'category' => 'general',
            'schema_version' => 1,
            'contact_json' => [],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => 'accredited',
            'is_active' => true,
        ])->id;
        tenancy()->end();

        $prId = $this->createApprovedPr([
            ['description' => 'Contract test item', 'quantity' => 2, 'unit_price' => 1000],
        ]);

        return [$prId, $vendorId];
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Contract test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Contract spend test',
                'lines' => $lines,
            ]);
        $create->assertCreated();
        $prId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementPr::query()->find($prId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        return $prId;
    }

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'PR contract test',
                'status' => 'published',
                'metadata_json' => ['form_family' => 'purchase_requisition', 'use_approval_policy' => false],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                ],
                'steps' => [['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1]],
            ]);
        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseOrderForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'PO contract test',
                'status' => 'published',
                'metadata_json' => ['form_family' => 'purchase_order', 'use_approval_policy' => false],
                'fields' => [
                    ['type' => 'text', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'grand_total', 'label' => 'Grand total', 'validation' => ['required' => true]],
                ],
                'steps' => [['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1]],
            ]);
        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function approveSubmission(string $submissionId): void
    {
        $inbox = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');

        $this->assertNotEmpty($approvalId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", ['decision' => 'approved'])
            ->assertOk();
    }
}
