<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Support\ProcurementExportEntity;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementExportTest extends TestCase
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

    public function test_excel_pack_returns_valid_xlsx_for_current_month(): void
    {
        $this->seedExportFixtures();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/procurement-one/reports/excel-pack?period=current_month');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $binary = (string) $response->getContent();
        $this->assertStringStartsWith('PK', $binary);
        $this->assertGreaterThan(1000, strlen($binary));
    }

    public function test_csv_vendors_export_respects_column_map(): void
    {
        $this->seedExportFixtures();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/procurement-one/settings', [
                'export_column_maps' => [
                    ProcurementExportEntity::VENDORS => [
                        ['key' => 'vendor_code', 'label' => 'Vendor code', 'enabled' => true],
                        ['key' => 'company_name', 'label' => 'Company name', 'enabled' => true],
                        ['key' => 'tax_id', 'label' => 'Tax ID', 'enabled' => false],
                    ],
                ],
            ])
            ->assertOk();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/procurement-one/reports/vendors/export?period=current_month');

        $response->assertOk();
        $csv = (string) $response->streamedContent();
        $this->assertStringContainsString('Vendor code', $csv);
        $this->assertStringContainsString('Company name', $csv);
        $this->assertStringNotContainsString('Tax ID', $csv);
    }

    public function test_dashboard_includes_reporting_sections_on_enterprise(): void
    {
        $this->seedExportFixtures();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'p2p' => ['kpis', 'cycle_times'],
                'vendor_spend' => ['period_label', 'rows'],
                'finance_kpis',
            ],
        ]);
        $this->assertNotEmpty($response->json('data.p2p.kpis'));
        $this->assertNotEmpty($response->json('data.vendor_spend.rows'));
    }

    public function test_reporting_exports_blocked_on_professional_plan(): void
    {
        $this->testTenant->plan_tier = 'professional';
        $this->testTenant->save();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/procurement-one/reports/excel-pack?period=current_month')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reporting_exports']);
    }

    public function test_export_schedule_settings_persist(): void
    {
        $update = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson('/api/v1/procurement-one/settings', [
                'export_schedule' => [
                    'enabled' => true,
                    'day_of_month' => 5,
                    'hour' => 7,
                    'period' => 'previous_month',
                    'recipients' => ['finance@test.localhost'],
                ],
            ]);

        $update->assertOk();
        $update->assertJsonPath('data.export_schedule.enabled', true);
        $update->assertJsonPath('data.export_schedule.day_of_month', 5);
        $update->assertJsonPath('data.export_schedule.hour', 7);
        $update->assertJsonPath('data.export_schedule.period', 'previous_month');
        $update->assertJsonPath('data.export_schedule.recipients.0', 'finance@test.localhost');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/settings')
            ->assertOk()
            ->assertJsonPath('data.export_schedule.enabled', true)
            ->assertJsonPath('data.export_schedule.recipients.0', 'finance@test.localhost');
    }

    private function seedExportFixtures(): void
    {
        $this->createPurchaseRequisitionForm();
        $this->createPurchaseOrderForm();

        tenancy()->initialize($this->testTenant);
        ProcurementVendor::query()->create([
            'vendor_code' => 'VEND-EXP',
            'company_name' => 'Export Vendor',
            'tax_id' => 'TIN-EXP',
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
            ['description' => 'Export test item', 'quantity' => 1, 'unit_price' => 50000],
        ]);

        $po = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Export Vendor',
                'vendor_code' => 'VEND-EXP',
                'vendor_name' => 'Export Vendor',
                'lines' => [
                    ['description' => 'Export test item', 'quantity' => 1, 'unit_price' => 50000],
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
    }

    private function createApprovedPr(array $lines): string
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Export test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Reporting export test',
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
                'name' => 'Export PR form',
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
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseOrderForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Export PO form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_order',
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
