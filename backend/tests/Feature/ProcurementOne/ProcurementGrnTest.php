<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementGrnTest extends TestCase
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

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_partial_goods_receipt_updates_po_to_partially_received(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => 'Battery bank', 'quantity' => 10, 'unit_price' => 1000],
        ]);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Battery bank', 'quantity' => 10, 'unit_price' => 1000],
        ]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_received' => 4],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.grn.status', 'posted')
            ->assertJsonPath('data.grn.purchase_order.status', ProcurementPoStatus::PARTIALLY_RECEIVED);

        tenancy()->initialize($this->testTenant);
        $po = ProcurementPo::query()->find($poId);
        $this->assertSame(ProcurementPoStatus::PARTIALLY_RECEIVED, $po?->status);
        tenancy()->end();
    }

    public function test_full_goods_receipt_updates_po_to_received(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => 'Battery bank', 'quantity' => 5, 'unit_price' => 1000],
        ]);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Battery bank', 'quantity' => 5, 'unit_price' => 1000],
        ]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_received' => 5],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.grn.status', 'posted')
            ->assertJsonPath('data.grn.purchase_order.status', ProcurementPoStatus::RECEIVED);

        tenancy()->initialize($this->testTenant);
        $po = ProcurementPo::query()->find($poId);
        $this->assertSame(ProcurementPoStatus::RECEIVED, $po?->status);
        tenancy()->end();
    }

    public function test_over_receipt_beyond_tolerance_is_blocked(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => 'Battery bank', 'quantity' => 2, 'unit_price' => 1000],
        ]);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Battery bank', 'quantity' => 2, 'unit_price' => 1000],
        ]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_received' => 10],
                ],
            ]);

        $response->assertStatus(422);
    }

    public function test_goods_receipt_index_returns_pagination_meta(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => 'Cable tray', 'quantity' => 2, 'unit_price' => 500],
        ]);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Cable tray', 'quantity' => 2, 'unit_price' => 500],
        ]);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_received' => 2],
                ],
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/grns')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data');
    }

    public function test_goods_receipt_requires_enterprise_plan(): void
    {
        $this->testTenant->plan_tier = 'professional';
        $this->testTenant->save();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/grns')
            ->assertStatus(422);
    }

    public function test_posted_grn_print_endpoint_returns_payload(): void
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => 'Battery bank', 'quantity' => 5, 'unit_price' => 1000],
        ]);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Battery bank', 'quantity' => 5, 'unit_price' => 1000],
        ]);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_received' => 5],
                ],
            ]);

        $create->assertCreated();
        $grnId = (string) $create->json('data.grn.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/grns/{$grnId}/print")
            ->assertOk()
            ->assertJsonPath('data.document_no', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.lines.0.quantity_received', 5);
    }

    public function test_tolerance_warn_mode_stores_receipt_mismatches(): void
    {
        tenancy()->initialize($this->testTenant);
        app(ProcurementOneSettingsService::class)
            ->setJson('gr_receipt_policy', [
                'tolerance_percent' => 5,
                'mode' => 'warn',
            ]);
        tenancy()->end();

        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => 'Battery bank', 'quantity' => 10, 'unit_price' => 1000],
        ]);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Battery bank', 'quantity' => 10, 'unit_price' => 1000],
        ]);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'lines' => [
                    ['po_line_id' => $this->firstPoLineId($poId), 'quantity_received' => 10.3],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.warning', fn ($value) => is_string($value) && $value !== '');

        $grnId = (string) $create->json('data.grn.id');

        $show = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/grns/{$grnId}");

        $show->assertOk()
            ->assertJsonPath('data.mismatches.0.type', 'tolerance_over_receipt');
    }

    /**
     * @param  list<array{description: string, quantity: float|int, unit_price: float|int}>  $lines
     */
    private function createApprovedPr(?string $title = null, ?array $lines = null): string
    {
        $this->createPurchaseRequisitionForm();

        $lines ??= [
            ['description' => 'Battery bank', 'quantity' => 1, 'unit_price' => 150000],
        ];

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => $title ?? 'Tower battery bank replacement',
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

    /**
     * @param  list<array{description: string, quantity: float|int, unit_price: float|int}>  $lines
     */
    private function createApprovedPoFromPr(string $prId, array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Acme Supplies',
                'lines' => $lines,
            ]);

        $create->assertCreated();
        $poId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit")
            ->assertOk();

        $submissionId = (string) ProcurementPo::query()->find($poId)?->e_approval_submission_id;
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
                    ['type' => 'textarea', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                    ]]],
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
