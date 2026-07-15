<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Ticketing\Support\TicketingCategoryPackCatalog;
use App\Modules\Ticketing\Support\TicketingSourceCatalog;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementTicketingTest extends TestCase
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
                'ticketing',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_metadata_lists_procurement_one_source_module(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/metadata');

        $response->assertOk();
        $modules = collect($response->json('data.source_modules'))->pluck('id')->all();
        $this->assertContains(TicketingSourceCatalog::MODULE_PROCUREMENT_ONE, $modules);
    }

    public function test_delivery_delay_ticket_links_to_po_and_lists_as_related(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/ticketing/settings', [
                'apply_category_pack' => TicketingCategoryPackCatalog::PACK_PROCUREMENT_ONE,
            ])
            ->assertOk();

        $poId = $this->seedApprovedPoWithPastDeliveryDate();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/ticketing/tickets', [
                'title' => 'Delivery delay: PO-TEST',
                'description' => 'Vendor missed expected delivery date.',
                'category' => 'procurement_delivery_delay',
                'source_module' => TicketingSourceCatalog::MODULE_PROCUREMENT_ONE,
                'source_reference_type' => 'purchase_order',
                'source_reference_id' => $poId,
                'source_label' => 'PO-TEST',
                'links' => [
                    [
                        'link_module' => TicketingSourceCatalog::MODULE_PROCUREMENT_ONE,
                        'link_type' => 'purchase_order',
                        'link_id' => $poId,
                        'link_label' => 'PO-TEST',
                    ],
                ],
            ]);

        $create->assertCreated();
        $create->assertJsonPath('data.source_module', TicketingSourceCatalog::MODULE_PROCUREMENT_ONE);
        $create->assertJsonPath('data.source_reference_id', $poId);
        $ticketId = (string) $create->json('data.id');

        $related = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/ticketing/tickets?'.http_build_query([
                'source_module' => TicketingSourceCatalog::MODULE_PROCUREMENT_ONE,
                'source_reference_id' => $poId,
            ]));

        $related->assertOk();
        $this->assertSame($ticketId, (string) $related->json('data.0.id'));
        $this->assertSame('Delivery delay: PO-TEST', $related->json('data.0.title'));
    }

    public function test_apply_procurement_category_pack_merges_categories(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/ticketing/settings', [
                'apply_category_pack' => TicketingCategoryPackCatalog::PACK_PROCUREMENT_ONE,
            ]);

        $response->assertOk();
        $categories = $response->json('data.categories');
        $this->assertContains('procurement_delivery_delay', $categories);
        $this->assertContains('procurement_vendor_issue', $categories);
        $this->assertNotEmpty($response->json('data.category_packs'));
    }

    private function seedApprovedPoWithPastDeliveryDate(): string
    {
        $this->createPurchaseRequisitionForm();
        $this->createPurchaseOrderForm();

        tenancy()->initialize($this->testTenant);
        ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-TKT',
            'company_name' => 'Ticket Vendor',
            'tax_id' => 'TIN-TKT',
            'category' => 'general',
            'schema_version' => 1,
            'contact_json' => [],
            'banking_json' => [],
            'address_json' => [],
            'profile_json' => [],
            'accreditation_status' => 'accredited',
            'is_active' => true,
        ]);
        tenancy()->end();

        $prId = $this->createApprovedPr([
            ['description' => 'Delayed delivery item', 'quantity' => 1, 'unit_price' => 25000],
        ]);

        $po = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Ticket Vendor',
                'vendor_code' => 'VEND-TKT',
                'vendor_name' => 'Ticket Vendor',
                'delivery_date' => now()->subDays(5)->format('Y-m-d'),
                'lines' => [
                    ['description' => 'Delayed delivery item', 'quantity' => 1, 'unit_price' => 25000],
                ],
            ]);
        $po->assertCreated();
        $poId = (string) $po->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/submit")
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $submissionId = (string) ProcurementPo::query()->find($poId)?->e_approval_submission_id;
        tenancy()->end();
        $this->approveSubmission($submissionId);

        tenancy()->initialize($this->testTenant);
        $model = ProcurementPo::query()->find($poId);
        $this->assertNotNull($model);
        $this->assertSame(ProcurementPoStatus::APPROVED, $model->status);
        tenancy()->end();

        return $poId;
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Ticketing test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Ticketing integration test',
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

    private function createPurchaseRequisitionForm(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Ticketing PR form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ])
            ->assertCreated();
    }

    private function createPurchaseOrderForm(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Ticketing PO form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_order',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'textarea', 'name' => 'supplier', 'label' => 'Supplier', 'validation' => ['required' => true]],
                    ['type' => 'date', 'name' => 'delivery_date', 'label' => 'Delivery date', 'validation' => ['required' => false]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [
                        ['label' => 'Description', 'type' => 'text'],
                        ['label' => 'Qty', 'type' => 'number'],
                        ['label' => 'Unit price', 'type' => 'currency'],
                    ]]],
                    ['type' => 'currency', 'name' => 'grand_total', 'label' => 'Grand total', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ])
            ->assertCreated();
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
