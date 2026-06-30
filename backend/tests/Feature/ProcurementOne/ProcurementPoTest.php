<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementPoTest extends TestCase
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
                'project_one',
                'e_approval',
                'procurement_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'professional';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_partial_pr_conversion_allows_multiple_pos(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/prs/{$prId}")
            ->assertOk()
            ->assertJsonPath('data.estimated_total', 175000)
            ->assertJsonPath('data.status', ProcurementPrStatus::APPROVED);

        $first = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Acme Supplies',
                'lines' => [
                    ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 150000],
                ],
            ]);

        $first->assertCreated();
        $firstPoId = (string) $first->json('data.id');
        $this->assertEqualsWithDelta(168000.0, (float) $first->json('data.grand_total'), 0.01);

        $second = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Acme Supplies',
                'lines' => [
                    ['description' => 'Installation labor', 'quantity' => 1, 'unit_price' => 25000],
                ],
            ]);

        $second->assertCreated();
        $this->assertEqualsWithDelta(28000.0, (float) $second->json('data.grand_total'), 0.01);

        tenancy()->initialize($this->testTenant);
        $pr = ProcurementPr::query()->find($prId);
        $this->assertNotNull($pr);
        $this->assertSame(ProcurementPrStatus::CONVERTED, $pr->status);
        $this->assertEqualsWithDelta(175000.0, (float) $pr->committed_po_amount, 0.01);
        tenancy()->end();
    }

    public function test_consolidated_po_from_multiple_prs(): void
    {
        $this->createPurchaseOrderForm();
        $prA = $this->createApprovedPr('PR A', [['description' => 'Line A', 'quantity' => 1, 'unit_price' => 100000]]);
        $prB = $this->createApprovedPr('PR B', [['description' => 'Line B', 'quantity' => 1, 'unit_price' => 50000]]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/pos', [
                'pr_ids' => [$prA, $prB],
                'supplier' => 'Consolidated Vendor',
                'lines' => [
                    ['description' => 'From PR A', 'quantity' => 1, 'unit_price' => 100000, 'pr_id' => $prA],
                    ['description' => 'From PR B', 'quantity' => 1, 'unit_price' => 50000, 'pr_id' => $prB],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonCount(2, 'data.purchase_requisitions');
        $this->assertEqualsWithDelta(168000.0, (float) $response->json('data.grand_total'), 0.01);

        tenancy()->initialize($this->testTenant);
        $po = ProcurementPo::query()->find((string) $response->json('data.id'));
        $this->assertNotNull($po);
        $this->assertCount(2, $po->prLinks);
        tenancy()->end();
    }

    public function test_submit_and_approve_po_projects_to_procurement_one(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Acme Supplies',
                'lines' => [
                    ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 150000],
                ],
            ]);

        $create->assertCreated();
        $poId = (string) $create->json('data.id');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.po.status', ProcurementPoStatus::PENDING_APPROVAL)
            ->assertJsonPath('data.po.document_no', fn ($value) => is_string($value) && $value !== '');

        $submissionId = (string) $submit->json('data.po.e_approval_submission_id');
        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $po = ProcurementPo::query()->find($poId);
        $this->assertNotNull($po);
        $this->assertSame(ProcurementPoStatus::APPROVED, $po->status);
        $this->assertNotNull($po->approved_at);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/pos/{$poId}")
            ->assertOk()
            ->assertJsonPath('data.status', ProcurementPoStatus::APPROVED)
            ->assertJsonPath('data.e_approval_submission_id', $submissionId);
    }

    public function test_compose_values_sync_approvers_to_e_approval_submission(): void
    {
        $this->createPurchaseOrderFormWithApprovers();
        $prId = $this->createApprovedPr();
        $approverId = (string) $this->testTenantAdmin->id;

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'values' => [
                    'supplier' => 'Acme Supplies',
                    'line_items' => '{"rows":[{"0":"","1":"Battery bank","2":"EA","3":"1","4":"150000","5":"0","6":"150000"}]}',
                    'vatable_amount' => '150000',
                    'vat_amount' => '18000',
                    'grand_total' => '168000',
                    'total_amount' => '150000',
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $create->assertCreated();

        $poId = (string) $create->json('data.id');
        $submissionId = (string) $create->json('data.e_approval_submission_id');
        $this->assertNotEmpty($submissionId);

        tenancy()->initialize($this->testTenant);
        $po = ProcurementPo::query()->findOrFail($poId);
        $submission = EApprovalSubmission::query()->with('values.field')->findOrFail($submissionId);
        $procurementApprover = $submission->values->first(
            static fn ($value) => ($value->field?->name ?? '') === 'procurement_approver',
        );
        $storedComposeApprover = $po->metadata_json['compose_form_values']['procurement_approver'] ?? null;
        tenancy()->end();

        $this->assertSame($approverId, $procurementApprover?->value);
        $this->assertSame($approverId, $storedComposeApprover);

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.po.status', ProcurementPoStatus::PENDING_APPROVAL);

        tenancy()->initialize($this->testTenant);
        $submitted = EApprovalSubmission::query()->with('approvals')->findOrFail($submissionId);
        tenancy()->end();

        $this->assertSame('pending', $submitted->status);
        $this->assertGreaterThan(0, $submitted->approvals->count());
    }

    private function createApprovedPr(string $title = 'Tower battery bank replacement', ?array $lines = null): string
    {
        $this->createPurchaseRequisitionForm();

        $lines ??= [
            ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 150000],
            ['description' => 'Installation labor', 'quantity' => 1, 'unit_price' => 25000],
        ];

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => $title,
                'department' => 'operations',
                'urgency' => 'urgent',
                'justification' => 'Critical site power resilience upgrade.',
                'lines' => $lines,
            ]);

        $create->assertCreated();
        $prId = (string) $create->json('data.id');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit");

        $submit->assertOk();
        $this->approveSubmission((string) $submit->json('data.pr.e_approval_submission_id'));

        return $prId;
    }

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition',
                'description' => 'PR test form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'print_template_kind' => 'purchase_requisition',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal'], ['value' => 'urgent', 'label' => 'Urgent']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseOrderForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase order',
                'description' => 'PO test form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_order',
                    'print_template_kind' => 'purchase_order',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'purchase_requisition_document_no', 'label' => 'PR No.', 'validation' => ['required' => false]],
                    ['type' => 'text', 'name' => 'vendor', 'label' => 'Vendor', 'validation' => ['required' => false]],
                    ['type' => 'textarea', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [
                        ['label' => 'Item', 'type' => 'text'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'UOM', 'type' => 'text'],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                        ['label' => 'Discount', 'type' => 'currency'],
                        ['label' => 'Amount', 'type' => 'currency'],
                    ]]],
                    ['type' => 'currency', 'name' => 'vatable_amount', 'label' => 'Vatable', 'validation' => ['required' => false]],
                    ['type' => 'currency', 'name' => 'vat_amount', 'label' => 'VAT', 'validation' => ['required' => false]],
                    ['type' => 'currency', 'name' => 'grand_total', 'label' => 'Grand total', 'validation' => ['required' => true]],
                    ['type' => 'currency', 'name' => 'total_amount', 'label' => 'Total', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseOrderFormWithApprovers(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase order with approvers',
                'description' => 'PO compose approver sync test form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_order',
                    'print_template_kind' => 'purchase_order',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'purchase_requisition_document_no', 'label' => 'PR No.', 'validation' => ['required' => false]],
                    ['type' => 'textarea', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [
                        ['label' => 'Item', 'type' => 'text'],
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'UOM', 'type' => 'text'],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                        ['label' => 'Discount', 'type' => 'currency'],
                        ['label' => 'Amount', 'type' => 'currency'],
                    ]]],
                    ['type' => 'currency', 'name' => 'vatable_amount', 'label' => 'Vatable', 'validation' => ['required' => false]],
                    ['type' => 'currency', 'name' => 'vat_amount', 'label' => 'VAT', 'validation' => ['required' => false]],
                    ['type' => 'currency', 'name' => 'grand_total', 'label' => 'Grand total', 'validation' => ['required' => true]],
                    ['type' => 'currency', 'name' => 'total_amount', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'procurement_approver', 'label' => 'Procurement approver', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'finance_approver', 'label' => 'Finance approver', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
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
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }
}
