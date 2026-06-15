<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalSubmissionFinanceAuditTest extends TestCase
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

    public function test_liquidation_submit_logs_parent_link_audit(): void
    {
        $this->setLiquidationPolicy(requiresParent: true, mode: 'block', percent: 0);

        [$caSubmissionId, $liqFormId] = $this->createApprovedCaAndLiquidationForms(5000);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $liqFormId,
                'parent_submission_id' => $caSubmissionId,
                'values' => [
                    'total_reimbursement' => '4000',
                ],
            ]);

        $response->assertCreated();
        $childSubmissionId = (string) $response->json('data.id');

        $log = $this->findAuditLog('parent_submission_linked', $childSubmissionId);
        $this->assertNotNull($log);

        $remarks = $this->decodeRemarks($log);
        $this->assertSame($caSubmissionId, $remarks['parent_submission_id']);
        $this->assertSame('cash_advance', $remarks['parent_form_family']);
        $this->assertNull($remarks['previous_parent_submission_id']);
    }

    public function test_warn_mode_liquidation_logs_structured_overspend_audit(): void
    {
        $this->setLiquidationPolicy(requiresParent: true, mode: 'warn', percent: 10);

        [$caSubmissionId, $liqFormId] = $this->createApprovedCaAndLiquidationForms(5000);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $liqFormId,
                'parent_submission_id' => $caSubmissionId,
                'values' => [
                    'total_reimbursement' => '5250',
                ],
            ]);

        $response->assertCreated();
        $childSubmissionId = (string) $response->json('data.id');

        $log = $this->findAuditLog('liquidation_overspend_allowed', $childSubmissionId);
        $this->assertNotNull($log);

        $remarks = $this->decodeRemarks($log);
        $this->assertSame('liquidation', $remarks['policy_kind']);
        $this->assertSame($caSubmissionId, $remarks['parent_submission_id']);
        $this->assertEqualsWithDelta(5250, (float) $remarks['amount'], 0.01);
        $this->assertEqualsWithDelta(5000, (float) $remarks['strict_open_balance'], 0.01);
        $this->assertEqualsWithDelta(5500, (float) $remarks['policy_max_amount'], 0.01);
        $this->assertNotEmpty($remarks['message']);
    }

    public function test_warn_mode_po_logs_structured_overspend_audit(): void
    {
        $this->setProcurementPolicy('warn', 10);

        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(10000);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '10500',
                ],
            ]);

        $response->assertCreated();
        $childSubmissionId = (string) $response->json('data.id');

        $log = $this->findAuditLog('po_overspend_allowed', $childSubmissionId);
        $this->assertNotNull($log);

        $remarks = $this->decodeRemarks($log);
        $this->assertSame('purchase_order', $remarks['policy_kind']);
        $this->assertSame($prSubmissionId, $remarks['parent_submission_id']);
        $this->assertEqualsWithDelta(10500, (float) $remarks['amount'], 0.01);
        $this->assertEqualsWithDelta(10000, (float) $remarks['strict_open_balance'], 0.01);
        $this->assertEqualsWithDelta(11000, (float) $remarks['policy_max_amount'], 0.01);
    }

    public function test_draft_update_logs_parent_submission_changed(): void
    {
        $this->setLiquidationPolicy(requiresParent: true, mode: 'block', percent: 0);

        [$caSubmissionId, $liqFormId] = $this->createApprovedCaAndLiquidationForms(5000);
        [$otherCaSubmissionId] = $this->createApprovedCaAndLiquidationForms(3000);

        $draftRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $liqFormId,
                'parent_submission_id' => $caSubmissionId,
                'values' => [
                    'total_reimbursement' => '1000',
                ],
                'as_draft' => true,
            ]);

        $draftRes->assertCreated();
        $draftId = (string) $draftRes->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$draftId}/draft", [
                'parent_submission_id' => $otherCaSubmissionId,
                'values' => [
                    'total_reimbursement' => '1000',
                ],
            ])
            ->assertOk();

        $log = $this->findAuditLog('parent_submission_changed', $draftId);
        $this->assertNotNull($log);

        $remarks = $this->decodeRemarks($log);
        $this->assertSame($otherCaSubmissionId, $remarks['parent_submission_id']);
        $this->assertSame($caSubmissionId, $remarks['previous_parent_submission_id']);
    }

    private function setLiquidationPolicy(bool $requiresParent, string $mode, int $percent): void
    {
        tenancy()->initialize($this->testTenant);

        $settings = app(EApprovalSettingsService::class);
        $settings->setString(
            EApprovalSettingsService::LIQUIDATION_REQUIRES_PARENT,
            $requiresParent ? 'true' : 'false',
        );
        $settings->setString(EApprovalSettingsService::LIQUIDATION_OVERSPEND_MODE, $mode);
        $settings->setString(EApprovalSettingsService::LIQUIDATION_MAX_OVERSPEND_PERCENT, (string) $percent);

        tenancy()->end();
    }

    private function setProcurementPolicy(string $mode, int $percent): void
    {
        tenancy()->initialize($this->testTenant);

        $settings = app(EApprovalSettingsService::class);
        $settings->setString(EApprovalSettingsService::PO_OVERSPEND_MODE, $mode);
        $settings->setString(EApprovalSettingsService::PO_MAX_OVERSPEND_PERCENT, (string) $percent);

        tenancy()->end();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createApprovedCaAndLiquidationForms(float $requestedAmount): array
    {
        $caFormId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'cash_advance'],
        );

        $caSubmissionId = $this->createSubmission($caFormId, [
            'requested_amount' => (string) $requestedAmount,
        ]);
        $this->approveSubmission($caSubmissionId);

        $liqFormId = $this->createPublishedForm(
            'Liquidation',
            [
                ['type' => 'currency', 'name' => 'total_reimbursement', 'label' => 'Total', 'validation' => ['required' => true]],
            ],
            [
                'form_family' => 'liquidation',
                'parent_form_family' => 'cash_advance',
                'requires_parent_submission' => true,
            ],
        );

        return [$caSubmissionId, $liqFormId];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createApprovedPrAndPoForms(float $prAmount): array
    {
        $prFormId = $this->createPublishedForm(
            'Purchase requisition',
            [
                ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_requisition'],
        );

        $prSubmissionId = $this->createSubmission($prFormId, [
            'estimated_total' => (string) $prAmount,
        ]);
        $this->approveSubmission($prSubmissionId);

        $poFormId = $this->createPublishedForm(
            'Purchase order',
            [
                ['type' => 'currency', 'name' => 'total_amount', 'label' => 'Total', 'validation' => ['required' => true]],
            ],
            [
                'form_family' => 'purchase_order',
                'parent_form_family' => 'purchase_requisition',
                'requires_parent_submission' => true,
            ],
        );

        return [$prSubmissionId, $poFormId];
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
                'description' => 'Finance audit test',
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
    private function createSubmission(string $formId, array $values): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => $values,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.id');
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

    private function findAuditLog(string $action, string $targetId): ?EApprovalAuditLog
    {
        tenancy()->initialize($this->testTenant);

        try {
            return EApprovalAuditLog::query()
                ->where('action', $action)
                ->where('target_id', $targetId)
                ->latest('created_at')
                ->first();
        } finally {
            tenancy()->end();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeRemarks(EApprovalAuditLog $log): array
    {
        $decoded = json_decode((string) $log->remarks, true);

        return is_array($decoded) ? $decoded : [];
    }
}
