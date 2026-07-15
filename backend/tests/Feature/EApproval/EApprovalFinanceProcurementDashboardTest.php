<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Config;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFinanceProcurementDashboardTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_dashboard_omits_finance_procurement_kpis_when_modules_disabled(): void
    {
        $previous = config('toweros.tenant_modules.enabled');
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'e_approval',
            'project_one',
        ]);

        try {
            $response = $this->actingAsTenantAdmin()
                ->withHeaders($this->tenantApiHeaders())
                ->getJson('/api/v1/e-approval/dashboard');

            $response->assertOk()
                ->assertJsonPath('data.finance_kpis', [])
                ->assertJsonPath('data.finance_counts', []);
        } finally {
            Config::set('toweros.tenant_modules.enabled', $previous);
        }
    }

    public function test_dashboard_includes_finance_procurement_kpis(): void
    {
        $caFormId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
                ['type' => 'textarea', 'name' => 'purpose', 'label' => 'Purpose', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'cash_advance'],
        );

        $caSubmissionId = $this->createSubmission($caFormId, [
            'requested_amount' => '5000',
            'purpose' => 'Travel',
        ]);
        $this->approveSubmission($caSubmissionId);

        $prFormId = $this->createPublishedForm(
            'Purchase requisition',
            [
                ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_requisition'],
        );

        $prSubmissionId = $this->createSubmission($prFormId, [
            'requisition_title' => 'Tower hardware',
            'estimated_total' => '12000',
        ]);
        $this->approveSubmission($prSubmissionId);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.finance_counts.open_cash_advances', 1)
            ->assertJsonPath('data.finance_counts.unliquidated_cash_advances', 1)
            ->assertJsonPath('data.finance_counts.prs_without_po', 1)
            ->assertJsonPath('data.finance_kpis.0.key', 'open_cash_advances')
            ->assertJsonPath('data.finance_kpis.0.value', '1')
            ->assertJsonPath('data.finance_kpis.1.key', 'unliquidated_cash_advances')
            ->assertJsonPath('data.finance_kpis.1.value', '1')
            ->assertJsonPath('data.finance_kpis.2.key', 'prs_without_po')
            ->assertJsonPath('data.finance_kpis.2.value', '1');
    }

    public function test_partial_liquidation_reduces_unliquidated_count_only(): void
    {
        $caFormId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
                ['type' => 'textarea', 'name' => 'purpose', 'label' => 'Purpose', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'cash_advance'],
        );

        $caSubmissionId = $this->createSubmission($caFormId, [
            'requested_amount' => '5000',
            'purpose' => 'Travel',
        ]);
        $this->approveSubmission($caSubmissionId);

        $liqFormId = $this->createPublishedForm(
            'Liquidation',
            [
                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],
        );

        $liqSubmissionId = $this->createSubmission($liqFormId, [
            'total_reimbursement' => '2000',
        ], null, $caSubmissionId);
        $this->approveSubmission($liqSubmissionId);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.finance_counts.open_cash_advances', 1)
            ->assertJsonPath('data.finance_counts.unliquidated_cash_advances', 0);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $metadata
     */
    private function createPublishedForm(string $name, array $fields, array $metadata = []): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => $name,
                'description' => 'Finance dashboard KPI test',
                'status' => 'published',
                'metadata_json' => $metadata,
                'fields' => $fields,
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function createSubmission(
        string $formId,
        array $values,
        ?TenantUser $actor = null,
        ?string $parentSubmissionId = null,
    ): string {
        $actor ??= $this->testTenantAdmin;

        $payload = [
            'form_id' => $formId,
            'values' => $values,
        ];

        if ($parentSubmissionId !== null) {
            $payload['parent_submission_id'] = $parentSubmissionId;
        }

        $response = $this->actingAs($actor)
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', $payload);

        $response->assertCreated();

        return (string) $response->json('data.id');
    }

    private function approveSubmission(string $submissionId, ?TenantUser $approver = null): void
    {
        $approver ??= $this->testTenantAdmin;

        $inbox = $this->actingAs($approver)
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');

        $this->assertNotEmpty($approvalId);

        $this->actingAs($approver)
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }
}
