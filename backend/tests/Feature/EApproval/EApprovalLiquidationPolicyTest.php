<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalLiquidationPolicyTest extends TestCase
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

    public function test_warn_mode_allows_liquidation_over_open_balance_within_policy_percent(): void
    {
        $this->setLiquidationPolicy(requiresParent: true, mode: 'warn', percent: 10);

        [$caSubmissionId, $liqFormId] = $this->createApprovedCaAndLiquidationForms(5000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $liqFormId,
                'parent_submission_id' => $caSubmissionId,
                'values' => [
                    'total_reimbursement' => '5250',
                ],
            ])
            ->assertCreated();
    }

    public function test_warn_mode_blocks_liquidation_beyond_policy_percent(): void
    {
        $this->setLiquidationPolicy(requiresParent: true, mode: 'warn', percent: 10);

        [$caSubmissionId, $liqFormId] = $this->createApprovedCaAndLiquidationForms(5000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $liqFormId,
                'parent_submission_id' => $caSubmissionId,
                'values' => [
                    'total_reimbursement' => '5600',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total_reimbursement']);
    }

    public function test_liquidation_parent_not_required_when_tenant_policy_disabled(): void
    {
        $this->setLiquidationPolicy(requiresParent: false, mode: 'block', percent: 0);

        [, $liqFormId] = $this->createApprovedCaAndLiquidationForms(5000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $liqFormId,
                'values' => [
                    'total_reimbursement' => '1000',
                ],
            ])
            ->assertCreated();
    }

    public function test_settings_api_exposes_finance_procurement_policy(): void
    {
        $this->setLiquidationPolicy(requiresParent: true, mode: 'warn', percent: 5);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/settings')
            ->assertOk()
            ->assertJsonPath('data.finance_procurement_policy.liquidation_requires_parent', true)
            ->assertJsonPath('data.finance_procurement_policy.liquidation_overspend_mode', 'warn')
            ->assertJsonPath('data.finance_procurement_policy.liquidation_max_overspend_percent', 5);
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
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $metadata
     */
    private function createPublishedForm(string $name, array $fields, array $metadata = []): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => $name,
                'description' => 'Liquidation policy test',
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
}
