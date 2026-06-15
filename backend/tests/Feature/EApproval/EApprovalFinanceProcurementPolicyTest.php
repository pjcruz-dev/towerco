<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFinanceProcurementPolicyTest extends TestCase
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

    public function test_warn_mode_allows_po_over_open_balance_within_policy_percent(): void
    {
        $this->setProcurementPolicy('warn', 10);

        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(10000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '10500',
                ],
            ])
            ->assertCreated();
    }

    public function test_warn_mode_blocks_po_beyond_policy_percent(): void
    {
        $this->setProcurementPolicy('warn', 10);

        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(10000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '11100',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total_amount']);
    }

    public function test_warn_mode_allows_second_po_within_cumulative_policy_buffer(): void
    {
        $this->setProcurementPolicy('warn', 10);

        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(10000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '9500',
                ],
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '1200',
                ],
            ])
            ->assertCreated();
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
    private function createApprovedPrAndPoForms(float $estimatedTotal = 10000): array
    {
        $prFormId = $this->createPublishedForm(
            'Purchase requisition',
            [
                ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_requisition'],
        );

        $prSubmissionId = $this->createSubmission($prFormId, [
            'estimated_total' => (string) $estimatedTotal,
        ]);
        $this->approveSubmission($prSubmissionId);

        $poFormId = $this->createPublishedForm(
            'Purchase order',
            [
                ['type' => 'currency', 'name' => 'total_amount', 'label' => 'PO total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_order', 'parent_form_family' => 'purchase_requisition'],
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
                'description' => 'Procurement policy test',
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
