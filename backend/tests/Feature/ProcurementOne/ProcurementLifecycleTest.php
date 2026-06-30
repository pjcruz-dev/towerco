<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementLifecycleTest extends TestCase
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

    public function test_void_approved_po_releases_pr_open_balance(): void
    {
        Notification::fake();

        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr();
        $this->seedVendor('ACME', 'vendor@acme.test');

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'vendor_code' => 'ACME',
                'supplier' => 'Acme Supplies',
                'lines' => [
                    ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 150000],
                ],
            ]);

        $create->assertCreated();
        $poId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit")
            ->assertOk();

        $submissionId = (string) ProcurementPo::query()->find($poId)?->e_approval_submission_id;
        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $prBeforeVoid = ProcurementPr::query()->find($prId);
        $this->assertSame(ProcurementPrStatus::CONVERTED, $prBeforeVoid?->status);
        tenancy()->end();

        $void = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/void", [
                'reason' => 'Vendor cannot supply on schedule.',
            ]);

        $void->assertOk()
            ->assertJsonPath('data.status', ProcurementPoStatus::VOIDED)
            ->assertJsonPath('data.void_reason', 'Vendor cannot supply on schedule.');

        tenancy()->initialize($this->testTenant);
        $pr = ProcurementPr::query()->find($prId);
        $po = ProcurementPo::query()->find($poId);
        $this->assertNotNull($pr);
        $this->assertNotNull($po);
        $this->assertSame(ProcurementPrStatus::APPROVED, $pr->status);
        $this->assertEqualsWithDelta(0.0, (float) $pr->committed_po_amount, 0.01);
        $this->assertGreaterThan(0, (float) $pr->estimated_total);
        tenancy()->end();
    }

    public function test_cancel_pending_po_requires_reason_and_restores_pr_balance(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Acme Supplies',
                'lines' => [
                    ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 50000],
                ],
            ]);

        $create->assertCreated();
        $poId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit")
            ->assertOk();

        $cancelWithoutReason = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/cancel");

        $cancelWithoutReason->assertStatus(422);

        $cancel = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/cancel", [
                'reason' => 'Duplicate PO created in error.',
            ]);

        $cancel->assertOk()
            ->assertJsonPath('data.status', ProcurementPoStatus::CANCELLED);

        tenancy()->initialize($this->testTenant);
        $pr = ProcurementPr::query()->find($prId);
        $this->assertSame(ProcurementPrStatus::APPROVED, $pr?->status);
        $this->assertEqualsWithDelta(0.0, (float) $pr?->committed_po_amount, 0.01);
        tenancy()->end();
    }

    public function test_vendor_email_settings_are_exposed_and_send_endpoint_validates_recipient(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr();

        $settings = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/settings');

        $settings->assertOk()
            ->assertJsonPath('data.vendor_email_templates.po_sent.enabled', true);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'No Email Vendor',
                'lines' => [
                    ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 50000],
                ],
            ]);

        $create->assertCreated();
        $poId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit")
            ->assertOk();

        $submissionId = (string) ProcurementPo::query()->find($poId)?->e_approval_submission_id;
        $this->approveSubmission($submissionId);

        $send = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/send-vendor-email");

        $send->assertStatus(422);
    }

    private function seedVendor(string $code, string $email): void
    {
        tenancy()->initialize($this->testTenant);
        ProcurementVendor::query()->create([
            'vendor_code' => $code,
            'company_name' => 'Acme Supplies',
            'tax_id' => $code,
            'category' => 'general',
            'schema_version' => 1,
            'contact_json' => ['email' => $email],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => 'accredited',
            'is_active' => true,
        ]);

        app(ProcurementOneSettingsService::class)->setJson('vendor_email_templates', [
            'auto_on_approve' => false,
            'auto_on_sent' => false,
            'po_approved' => ['enabled' => true, 'subject' => 'PO {{document_no}}', 'body' => 'Approved {{document_no}}'],
            'po_sent' => ['enabled' => true, 'subject' => 'PO {{document_no}}', 'body' => 'Sent {{document_no}}'],
            'po_cancelled' => ['enabled' => true, 'subject' => 'Cancelled', 'body' => 'Cancelled {{reason}}'],
            'po_voided' => ['enabled' => true, 'subject' => 'Voided', 'body' => 'Voided {{reason}}'],
        ]);
        tenancy()->end();
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
