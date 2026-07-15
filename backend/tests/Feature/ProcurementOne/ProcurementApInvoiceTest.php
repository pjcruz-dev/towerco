<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementApInvoiceTest extends TestCase
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

    public function test_ap_invoice_matches_po_and_grn_then_submits_for_approval(): void
    {
        $this->createApInvoiceForm();
        [$poId, $grnId] = $this->createApprovedPoWithPostedGrn();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/ap-invoices", [
                'grn_id' => $grnId,
                'vendor_invoice_no' => 'VINV-2026-001',
                'invoice_date' => '2026-06-01',
                'due_date' => '2026-07-01',
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_invoiced' => 2, 'unit_price' => 1000],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.invoice.match_status', 'matched')
            ->assertJsonPath('data.invoice.vendor_invoice_no', 'VINV-2026-001');

        $invoiceId = (string) $create->json('data.invoice.id');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/ap-invoices/{$invoiceId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.invoice.status', 'pending_approval');

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementApInvoice::query()->find($invoiceId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $invoice = ProcurementApInvoice::query()->find($invoiceId);
        $this->assertSame(ProcurementApInvoiceStatus::APPROVED, (string) $invoice?->status);
        tenancy()->end();
    }

    public function test_gl_export_returns_csv_for_approved_invoices(): void
    {
        $this->createApInvoiceForm();
        [$poId, $grnId] = $this->createApprovedPoWithPostedGrn();
        $poLineId = $this->firstPoLineId($poId);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/ap-invoices", [
                'grn_id' => $grnId,
                'vendor_invoice_no' => 'VINV-EXPORT-001',
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_invoiced' => 1, 'unit_price' => 500],
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

        $export = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/procurement-one/ap-invoices/export');

        $export->assertOk();
        $this->assertStringContainsString('text/csv', (string) $export->headers->get('Content-Type'));
        $this->assertStringContainsString('document_no', $export->streamedContent());
    }

    public function test_credit_note_reduces_open_ap_balance(): void
    {
        $this->createApInvoiceForm();
        [$poId, $grnId] = $this->createApprovedPoWithPostedGrn();
        $poLineId = $this->firstPoLineId($poId);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/ap-invoices", [
                'grn_id' => $grnId,
                'vendor_invoice_no' => 'VINV-CN-001',
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_invoiced' => 1, 'unit_price' => 1000],
                ],
            ]);
        $invoiceId = (string) $create->json('data.invoice.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/ap-invoices/{$invoiceId}/submit")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementApInvoice::query()->find($invoiceId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        $beforeAging = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/ap-invoices/aging');
        $beforeAging->assertOk();
        $openBefore = (float) $beforeAging->json('data.total_open');
        $this->assertGreaterThan(0, $openBefore);

        $credit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/credit-notes', [
                'po_id' => $poId,
                'ap_invoice_id' => $invoiceId,
                'amount' => 200,
                'reason' => 'Partial return',
            ]);
        $credit->assertCreated();
        $creditId = (string) $credit->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/credit-notes/{$creditId}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $afterAging = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/ap-invoices/aging');
        $afterAging->assertOk();
        $this->assertEqualsWithDelta($openBefore - 200, (float) $afterAging->json('data.total_open'), 0.01);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createApprovedPoWithPostedGrn(): array
    {
        $this->createPurchaseRequisitionForm();
        $this->createPurchaseOrderForm();

        $prId = $this->createApprovedPr([
            ['description' => 'AP test item', 'quantity' => 2, 'unit_price' => 1000],
        ]);
        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'AP test item', 'quantity' => 2, 'unit_price' => 1000],
        ]);
        $poLineId = $this->firstPoLineId($poId);

        $grn = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_received' => 2],
                ],
            ]);
        $grn->assertCreated();
        $grnId = (string) $grn->json('data.grn.id');

        return [$poId, $grnId];
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'AP test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'AP invoice test',
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
                'name' => 'AP invoice test',
                'description' => 'AP invoice approval',
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
                'name' => 'PR AP test',
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
                'name' => 'PO AP test',
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
