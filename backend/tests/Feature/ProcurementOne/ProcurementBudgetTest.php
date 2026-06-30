<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Services\ProcurementOneSettingsService;
use App\Modules\ProcurementOne\Services\ProcurementPrBudgetPolicyService;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementBudgetTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private string $rolloutId = '';

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
                'finance_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->rolloutId = $this->seedRollout();
        app(ProcurementOneSettingsService::class)->setJson(ProcurementPrBudgetPolicyService::SETTINGS_KEY, [
            'enabled' => true,
            'mode' => 'block',
        ]);
        tenancy()->end();
    }

    public function test_budget_line_sets_rollout_budget_total(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/budget-lines', [
                'rollout_id' => $this->rolloutId,
                'description' => 'Civil works envelope',
                'expense_type' => 'capex',
                'budget_amount' => 250000,
            ])
            ->assertCreated()
            ->assertJsonPath('data.budget_amount', 250000);

        $utilization = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/budget-utilization?rollout_id='.$this->rolloutId);

        $utilization->assertOk()
            ->assertJsonPath('data.budget_total', 250000)
            ->assertJsonPath('data.committed', 0)
            ->assertJsonPath('data.available', 250000)
            ->assertJsonPath('data.utilization_percent', 0);
    }

    public function test_approved_pr_and_open_po_reduce_available_budget(): void
    {
        $this->seedBudgetLine(100000);

        $prId = $this->createApprovedPrWithRollout([
            ['description' => 'Tower materials', 'quantity' => 1, 'unit_price' => 30000],
        ]);

        $afterPr = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/budget-utilization?rollout_id='.$this->rolloutId);

        $afterPr->assertOk()
            ->assertJsonPath('data.budget_total', 100000)
            ->assertJsonPath('data.committed_pr', 30000)
            ->assertJsonPath('data.committed_po', 0)
            ->assertJsonPath('data.available', 70000);

        $poId = $this->createApprovedPoFromPr($prId, [
            ['description' => 'Tower materials', 'quantity' => 1, 'unit_price' => 30000],
        ]);

        tenancy()->initialize($this->testTenant);
        $poGrandTotal = (float) ProcurementPo::query()->find($poId)?->grand_total;
        tenancy()->end();

        $afterPo = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/budget-utilization?rollout_id='.$this->rolloutId);

        $afterPo->assertOk()
            ->assertJsonPath('data.budget_total', 100000);

        $this->assertEqualsWithDelta($poGrandTotal, (float) $afterPo->json('data.committed_po'), 0.01);
        $this->assertEqualsWithDelta($poGrandTotal, (float) $afterPo->json('data.committed'), 0.01);
        $this->assertEqualsWithDelta(round(100000 - $poGrandTotal, 2), (float) $afterPo->json('data.available'), 0.01);
    }

    public function test_dashboard_aggregates_budget_across_multiple_rollouts(): void
    {
        Cache::flush();

        tenancy()->initialize($this->testTenant);
        $secondRolloutId = (string) RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-BUDGET-002',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 120,
        ])->id;
        tenancy()->end();

        $this->seedBudgetLine(100000);
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/budget-lines', [
                'rollout_id' => $secondRolloutId,
                'description' => 'Second rollout envelope',
                'expense_type' => 'capex',
                'budget_amount' => 50000,
            ])
            ->assertCreated();

        $this->createApprovedPrWithRollout([
            ['description' => 'Rollout one materials', 'quantity' => 1, 'unit_price' => 25000],
        ]);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Second rollout PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Second rollout encumbrance',
                'rollout_id' => $secondRolloutId,
                'lines' => [
                    ['description' => 'Rollout two materials', 'quantity' => 1, 'unit_price' => 10000],
                ],
            ])
            ->assertCreated();

        $dashboard = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/dashboard');

        $dashboard->assertOk();

        $budgetKpis = collect($dashboard->json('data.budget_kpis'))->keyBy('key');
        $this->assertSame(150000.0, (float) $budgetKpis->get('budget_total')['value']);
        $this->assertSame(25000.0, (float) $budgetKpis->get('committed')['value']);
        $this->assertSame(125000.0, (float) $budgetKpis->get('available')['value']);
        $this->assertSame('16.7%', $budgetKpis->get('utilization_percent')['value']);
    }

    public function test_pr_submit_blocked_when_over_budget_in_block_mode(): void
    {
        $this->seedBudgetLine(10000);
        $this->createPurchaseRequisitionForm();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Over budget PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Budget block test',
                'rollout_id' => $this->rolloutId,
                'lines' => [
                    ['description' => 'Overspend line', 'quantity' => 1, 'unit_price' => 15000],
                ],
            ]);

        $create->assertCreated();
        $prId = (string) $create->json('data.id');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit");

        $submit->assertUnprocessable()
            ->assertJsonValidationErrors(['estimated_total']);
    }

    public function test_cost_center_can_be_created_and_listed(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/cost-centers', [
                'code' => 'OPS-01',
                'name' => 'Field operations',
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'OPS-01');

        $index = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/cost-centers');

        $index->assertOk()
            ->assertJsonCount(1, 'data.cost_centers')
            ->assertJsonPath('data.cost_centers.0.name', 'Field operations');
    }

    private function seedRollout(): string
    {
        $rollout = RolloutProgram::query()->create([
            'playbook_version' => 'v2',
            'rollout_ref' => 'RP-BUDGET-001',
            'mno' => 'glo',
            'project_type' => 'bts',
            'status' => 'permitting',
            'endorsement_date' => '2026-04-01',
            'tssr_approved_date' => '2026-04-28',
            'sla_working_days' => 120,
        ]);

        return (string) $rollout->id;
    }

    private function seedBudgetLine(float $amount): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/budget-lines', [
                'rollout_id' => $this->rolloutId,
                'description' => 'Rollout procurement envelope',
                'expense_type' => 'capex',
                'budget_amount' => $amount,
            ])
            ->assertCreated();
    }

    /**
     * @param  list<array{description: string, quantity: float|int, unit_price: float|int}>  $lines
     */
    private function createApprovedPrWithRollout(array $lines): string
    {
        $this->createPurchaseRequisitionForm();
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Budget test PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Budget encumbrance test',
                'rollout_id' => $this->rolloutId,
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

    /**
     * @param  list<array{description: string, quantity: float|int, unit_price: float|int}>  $lines
     */
    private function createApprovedPoFromPr(string $prId, array $lines): string
    {
        $this->createPurchaseOrderForm();
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/pos", [
                'supplier' => 'Budget Vendor Co',
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

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition budget',
                'description' => 'PR budget test',
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
                'name' => 'Purchase order budget',
                'description' => 'PO budget test',
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
