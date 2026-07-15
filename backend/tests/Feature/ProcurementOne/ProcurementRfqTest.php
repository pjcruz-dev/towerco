<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Models\ProcurementRfq;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementRfqStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementRfqTest extends TestCase
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
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_rfq_from_pr_through_award_and_po_without_spreadsheets(): void
    {
        [$prId, $vendorA, $vendorB] = $this->bootstrapApprovedPrWithVendors();
        $this->createPurchaseOrderForm();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorA, $vendorB],
            ]);
        $create->assertCreated()
            ->assertJsonPath('data.rfq.status', 'draft');
        $rfqId = (string) $create->json('data.rfq.id');
        $lineId = (string) $create->json('data.rfq.lines.0.id');

        $duplicate = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/rfqs", [
                'vendor_ids' => [$vendorA, $vendorB],
            ]);
        $duplicate->assertStatus(422)
            ->assertJsonValidationErrors(['pr_id']);

        tenancy()->initialize($this->testTenant);
        ProcurementRfq::query()->create([
            'status' => ProcurementRfqStatus::DRAFT,
            'title' => 'Duplicate draft',
            'pr_id' => $prId,
            'requestor_id' => (string) $this->testTenantAdmin->id,
            'currency_code' => 'PHP',
            'estimated_total' => 1000,
            'metadata_json' => [],
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/publish")
            ->assertOk()
            ->assertJsonPath('data.rfq.status', 'open');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/bids", [
                'vendor_id' => $vendorA,
                'lines' => [
                    ['rfq_line_id' => $lineId, 'quantity' => 2, 'unit_price' => 1000, 'lead_time_days' => 14],
                ],
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/bids", [
                'vendor_id' => $vendorB,
                'lines' => [
                    ['rfq_line_id' => $lineId, 'quantity' => 2, 'unit_price' => 950, 'lead_time_days' => 10],
                ],
            ])
            ->assertCreated();

        $comparison = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}/comparison");
        $comparison->assertOk();
        $this->assertNotEmpty($comparison->json('data.rows'));
        $recommendedBidId = (string) $comparison->json('data.recommended_bid_id');
        $this->assertNotEmpty($recommendedBidId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/close-bidding")
            ->assertOk()
            ->assertJsonPath('data.rfq.status', 'closed');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/award", [
                'bid_id' => $recommendedBidId,
                'award_notes' => 'Best weighted score',
            ])
            ->assertOk()
            ->assertJsonPath('data.rfq.status', 'awarded');

        tenancy()->initialize($this->testTenant);
        $this->assertSame(
            0,
            ProcurementRfq::query()->where('pr_id', $prId)->where('status', ProcurementRfqStatus::DRAFT)->count(),
        );
        $this->assertSame(
            1,
            ProcurementRfq::query()->where('pr_id', $prId)->where('status', ProcurementRfqStatus::CANCELLED)->count(),
        );
        tenancy()->end();

        $po = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/rfqs/{$rfqId}/pos");
        $po->assertCreated();
        $poId = (string) $po->json('data.purchase_order.id');
        $this->assertNotEmpty($poId);

        $index = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/rfqs');
        $index->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $rfqId)
            ->assertJsonPath('data.0.status', 'converted');

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/rfqs/{$rfqId}");
        $detail->assertOk()
            ->assertJsonPath('data.rfq.status', 'converted')
            ->assertJsonPath('data.rfq.purchase_order.id', $poId);

        tenancy()->initialize($this->testTenant);
        $this->assertSame(ProcurementRfqStatus::CONVERTED, (string) ProcurementRfq::query()->find($rfqId)?->status);
        $this->assertNotNull(ProcurementPo::query()->find($poId));
        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function bootstrapApprovedPrWithVendors(): array
    {
        $this->createPurchaseRequisitionForm();
        tenancy()->initialize($this->testTenant);
        $vendorA = (string) ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-A',
            'company_name' => 'Vendor Alpha',
            'tax_id' => 'VEND-A',
            'category' => 'general',
            'schema_version' => 1,
            'contact_json' => [],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => 'accredited',
            'is_active' => true,
        ])->id;
        $vendorB = (string) ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-B',
            'company_name' => 'Vendor Beta',
            'tax_id' => 'VEND-B',
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
            ['description' => 'RFQ test item', 'quantity' => 2, 'unit_price' => 1000],
        ]);

        return [$prId, $vendorA, $vendorB];
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'RFQ test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'RFQ sourcing test',
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
                'name' => 'PR RFQ test',
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
                'name' => 'PO RFQ test',
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
