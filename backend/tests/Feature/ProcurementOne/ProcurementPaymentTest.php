<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementPaymentRequestStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementPaymentTest extends TestCase
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
        app(ProcurementOneSettingsService::class)->setJson('ap_invoice_match_policy', [
            'match_mode' => 'three_way',
            'tolerance_percent' => 2,
            'mode' => 'warn',
            'require_grn_posted' => true,
        ]);
        tenancy()->end();
    }

    public function test_payment_request_flow_through_reconciled_with_audit_trail(): void
    {
        $invoiceId = $this->createApprovedApInvoice();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/ap-invoices/{$invoiceId}/payment-requests");
        $create->assertCreated()
            ->assertJsonPath('data.payment_request.status', 'draft');

        $requestId = (string) $create->json('data.payment_request.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-requests/{$requestId}/submit")
            ->assertOk()
            ->assertJsonPath('data.payment_request.status', 'pending_approval');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-requests/{$requestId}/approve")
            ->assertOk()
            ->assertJsonPath('data.payment_request.status', 'approved')
            ->assertJsonPath('data.payment_request.document_no', fn ($v) => is_string($v) && $v !== '');

        $batch = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/payment-batches', [
                'payment_request_ids' => [$requestId],
                'scheduled_date' => '2026-06-20',
            ]);
        $batch->assertCreated()
            ->assertJsonPath('data.payment_batch.status', 'scheduled');
        $batchId = (string) $batch->json('data.payment_batch.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get("/api/v1/procurement-one/payment-batches/{$batchId}/export")
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-batches/{$batchId}/mark-exported")
            ->assertOk()
            ->assertJsonPath('data.payment_batch.status', 'exported');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-requests/{$requestId}/mark-paid", [
                'payment_reference' => 'BNK-2026-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.payment_request.status', 'paid')
            ->assertJsonPath('data.payment_request.payment_reference', 'BNK-2026-001');

        $beforeAging = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/ap-invoices/aging');
        $beforeAging->assertOk();
        $this->assertEquals(0.0, (float) $beforeAging->json('data.total_open'));

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-requests/{$requestId}/mark-reconciled")
            ->assertOk()
            ->assertJsonPath('data.payment_request.status', 'reconciled');

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/payment-requests/{$requestId}");
        $detail->assertOk();
        $audit = $detail->json('data.payment_request.audit_trail');
        $this->assertIsArray($audit);
        $this->assertNotEmpty($audit);
        $actions = collect($audit)->pluck('action')->all();
        $this->assertContains('created', $actions);
        $this->assertContains('approved', $actions);
        $this->assertContains('paid', $actions);
        $this->assertContains('reconciled', $actions);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(
            ProcurementPaymentRequestStatus::RECONCILED,
            (string) ProcurementPaymentRequest::query()->find($requestId)?->status,
        );
        tenancy()->end();
    }

    public function test_vendor_detail_includes_payment_history(): void
    {
        $invoiceId = $this->createApprovedApInvoice();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/ap-invoices/{$invoiceId}/payment-requests");
        $create->assertCreated();
        $requestId = (string) $create->json('data.payment_request.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-requests/{$requestId}/submit")
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/payment-requests/{$requestId}/approve")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $vendorCode = (string) ProcurementApInvoice::query()->find($invoiceId)?->purchaseOrder?->supplier;
        $vendor = ProcurementVendor::query()
            ->where('vendor_code', $vendorCode)
            ->orWhere('company_name', 'like', '%AP Vendor%')
            ->first();
        tenancy()->end();

        if ($vendor === null) {
            $this->markTestSkipped('No procurement vendor row for PO supplier in test tenant.');
        }

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/vendors/{$vendor->id}");
        $show->assertOk();
        $history = $show->json('data.payment_history');
        $this->assertIsArray($history);
        $this->assertNotEmpty($history);
    }

    private function createApprovedApInvoice(): string
    {
        $this->createApInvoiceForm();
        $this->createPurchaseRequisitionForm();
        $this->createPurchaseOrderForm();

        $prId = $this->createApprovedPr([
            ['description' => 'Payment test item', 'quantity' => 1, 'unit_price' => 1000],
        ]);
        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Payment test item', 'quantity' => 1, 'unit_price' => 1000],
        ]);
        $poLineId = $this->firstPoLineId($poId);

        $grn = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_received' => 1],
                ],
            ]);
        $grn->assertCreated();
        $grnId = (string) $grn->json('data.grn.id');

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/ap-invoices", [
                'grn_id' => $grnId,
                'vendor_invoice_no' => 'VINV-PAY-001',
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_invoiced' => 1, 'unit_price' => 1000],
                ],
            ]);
        $create->assertCreated();
        $invoiceId = (string) $create->json('data.invoice.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/ap-invoices/{$invoiceId}/submit")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementApInvoice::query()->find($invoiceId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $status = (string) ProcurementApInvoice::query()->find($invoiceId)?->status;
        tenancy()->end();
        $this->assertSame(ProcurementApInvoiceStatus::APPROVED, $status);

        return $invoiceId;
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Payment test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Payment tracking test',
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

    private function createApprovedPoFromPr(string $prId, array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'AP Vendor Ltd',
                'lines' => $lines,
            ]);
        $create->assertCreated();
        $poId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementPo::query()->find($poId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        return $poId;
    }

    private function firstPoLineId(string $poId): string
    {
        tenancy()->initialize($this->testTenant);
        $lineId = (string) ProcurementPo::query()->with('lines')->find($poId)?->lines->first()?->id;
        tenancy()->end();
        $this->assertNotEmpty($lineId);

        return $lineId;
    }

    private function createApInvoiceForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'AP invoice payment test',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'ap_invoice',
                    'print_template_kind' => 'ap_invoice',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'purchase_order_document_no', 'label' => 'PO no.', 'validation' => ['required' => true]],
                    ['type' => 'text', 'name' => 'vendor_invoice_no', 'label' => 'Vendor invoice no.', 'validation' => ['required' => true]],
                    ['type' => 'text', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'grand_total', 'label' => 'Total', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);
        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'PR payment test',
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
                'name' => 'PO payment test',
                'status' => 'published',
                'metadata_json' => ['form_family' => 'purchase_order', 'use_approval_policy' => false],
                'fields' => [
                    ['type' => 'text', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'grand_total', 'label' => 'Total', 'validation' => ['required' => true]],
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
