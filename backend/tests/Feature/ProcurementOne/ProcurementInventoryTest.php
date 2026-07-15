<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AssetOne\Models\Asset;
use App\Modules\ProcurementOne\Models\ProcurementInventoryLocation;
use App\Modules\ProcurementOne\Models\ProcurementInventoryStockBalance;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Support\ProcurementInventoryLocationKind;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementInventoryTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private string $towerOneLocationId = '';

    private string $towerTwoLocationId = '';

    private string $siteLocationId = '';

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
                'asset_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->seedInventoryLocations();
        app(ProcurementOneSettingsService::class)->setJson('inventory_policy', [
            'inventory_mode' => 'simple',
            'default_receipt_location_id' => $this->towerOneLocationId,
            'auto_create_assets_on_deploy' => true,
        ]);
        tenancy()->end();
    }

    public function test_posted_grn_records_stock_into_default_warehouse(): void
    {
        $poId = $this->createApprovedPoWithLine('Rectifier module', 3);
        $poLineId = $this->firstPoLineId($poId);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'inventory_location_id' => $this->towerOneLocationId,
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_received' => 3],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.grn.status', 'posted')
            ->assertJsonCount(1, 'data.grn.stock_movements');

        tenancy()->initialize($this->testTenant);
        $balance = ProcurementInventoryStockBalance::query()
            ->where('location_id', $this->towerOneLocationId)
            ->where('po_line_id', $poLineId)
            ->first();
        $this->assertNotNull($balance);
        $this->assertEqualsWithDelta(3.0, (float) $balance->quantity_on_hand, 0.0001);
        tenancy()->end();
    }

    public function test_transfer_moves_stock_between_warehouse_locations(): void
    {
        $poId = $this->createApprovedPoWithLine('Hybrid cable', 5);
        $poLineId = $this->firstPoLineId($poId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'inventory_location_id' => $this->towerOneLocationId,
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_received' => 5],
                ],
            ])
            ->assertCreated();

        $transfer = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/inventory/transfers', [
                'from_location_id' => $this->towerOneLocationId,
                'to_location_id' => $this->towerTwoLocationId,
                'po_line_id' => $poLineId,
                'quantity' => 2,
            ]);

        $transfer->assertCreated()
            ->assertJsonCount(2, 'data.movements');

        tenancy()->initialize($this->testTenant);
        $towerOne = ProcurementInventoryStockBalance::query()
            ->where('location_id', $this->towerOneLocationId)
            ->where('po_line_id', $poLineId)
            ->value('quantity_on_hand');
        $towerTwo = ProcurementInventoryStockBalance::query()
            ->where('location_id', $this->towerTwoLocationId)
            ->where('po_line_id', $poLineId)
            ->value('quantity_on_hand');
        $this->assertEqualsWithDelta(3.0, (float) $towerOne, 0.0001);
        $this->assertEqualsWithDelta(2.0, (float) $towerTwo, 0.0001);
        tenancy()->end();
    }

    public function test_deploy_creates_asset_record(): void
    {
        $poId = $this->createApprovedPoWithLine('Ericsson RRU', 1);
        $poLineId = $this->firstPoLineId($poId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/pos/{$poId}/grns", [
                'post' => true,
                'inventory_location_id' => $this->towerOneLocationId,
                'lines' => [
                    ['po_line_id' => $poLineId, 'quantity_received' => 1],
                ],
            ])
            ->assertCreated();

        $deploy = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/inventory/deployments', [
                'from_location_id' => $this->towerOneLocationId,
                'to_location_id' => $this->siteLocationId,
                'po_line_id' => $poLineId,
                'quantity' => 1,
                'create_asset' => true,
            ]);

        $deploy->assertCreated()
            ->assertJsonPath('data.asset.status', 'deployed')
            ->assertJsonPath('data.movement.movement_type', 'deploy');

        tenancy()->initialize($this->testTenant);
        $this->assertSame(1, Asset::query()->where('source_po_line_id', $poLineId)->count());
        tenancy()->end();
    }

    private function seedInventoryLocations(): void
    {
        $towerOne = ProcurementInventoryLocation::query()->create([
            'code' => 'TWR1-WH',
            'name' => 'Tower 1 Warehouse',
            'location_kind' => ProcurementInventoryLocationKind::WAREHOUSE,
            'is_default_receipt' => true,
            'is_active' => true,
        ]);
        $towerTwo = ProcurementInventoryLocation::query()->create([
            'code' => 'TWR2-WH',
            'name' => 'Tower 2 Warehouse',
            'location_kind' => ProcurementInventoryLocationKind::WAREHOUSE,
            'is_active' => true,
        ]);
        $site = ProcurementInventoryLocation::query()->create([
            'code' => 'SITE-BGC',
            'name' => 'BGC Site Staging',
            'location_kind' => ProcurementInventoryLocationKind::SITE,
            'is_active' => true,
        ]);

        $this->towerOneLocationId = (string) $towerOne->id;
        $this->towerTwoLocationId = (string) $towerTwo->id;
        $this->siteLocationId = (string) $site->id;
    }

    private function createApprovedPoWithLine(string $description, float $quantity): string
    {
        $this->createPurchaseOrderForm();
        $prId = $this->createApprovedPr(lines: [
            ['description' => $description, 'quantity' => $quantity, 'unit_price' => 1000],
        ]);

        return $this->createApprovedPoFromPr($prId, [
            ['description' => $description, 'quantity' => $quantity, 'unit_price' => 1000],
        ]);
    }

    private function createApprovedPr(array $lines): string
    {
        $this->createPurchaseRequisitionForm();
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Inventory test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Inventory phase test',
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
                'supplier' => 'Acme Supplies',
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

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition inventory',
                'description' => 'PR inventory test',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'print_template_kind' => 'purchase_requisition',
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
            ]);
        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseOrderForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase order inventory',
                'description' => 'PO inventory test',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_order',
                    'print_template_kind' => 'purchase_order',
                    'use_approval_policy' => false,
                ],
                'fields' => [
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
