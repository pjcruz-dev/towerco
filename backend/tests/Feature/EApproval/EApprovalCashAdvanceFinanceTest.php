<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Documents\Models\Site;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalCashAdvanceFinanceTest extends TestCase
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

    public function test_release_requires_a_reference_then_badge_says_released(): void
    {
        $formId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'cash_advance'],
            'release',
            'Release',
        );

        $submissionId = $this->createSubmission($formId, ['requested_amount' => '4000']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}")
            ->assertOk()
            ->assertJsonPath('data.status_label', 'Pending — Release');

        $approvalId = $this->pendingApprovalId($submissionId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['release_reference']);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
                'release_reference' => 'CV-100',
                'release_date' => '2026-10-13',
            ])
            ->assertOk();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved')
            ->assertJsonPath('data.status_label', 'Released');
    }

    public function test_cancel_trip_then_confirm_return(): void
    {
        $formId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'cash_advance'],
        );
        $submissionId = $this->createSubmission($formId, ['requested_amount' => '1500']);
        $this->approveSubmission($submissionId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/cancel-trip")
            ->assertOk()
            ->assertJsonPath('data.status_label', 'For return')
            ->assertJsonPath('data.finance_stage', 'for_return');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/confirm-return", [
                'settlement_reference' => 'OR-9',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_label', 'Funds returned')
            ->assertJsonPath('data.finance_stage', 'funds_returned');
    }

    public function test_block_new_cash_advance_while_one_is_unsettled(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalSettingsService::class)->setString(EApprovalSettingsService::BLOCK_NEW_CASH_ADVANCE, 'true');
        tenancy()->end();

        $formId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'cash_advance'],
        );
        $first = $this->createSubmission($formId, ['requested_amount' => '800']);
        $this->approveSubmission($first);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['requested_amount' => '100'],
            ])
            ->assertUnprocessable();
    }

    public function test_per_diem_cap_rejects_the_line(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalSettingsService::class)->setString(EApprovalSettingsService::PER_DIEM_MAX_PER_DAY, '500');
        tenancy()->end();

        $formId = $this->createPublishedForm(
            'Reimbursement',
            [
                [
                    'type' => 'grid',
                    'name' => 'expense_lines',
                    'label' => 'Expenses',
                    'validation' => ['required' => true],
                    'options' => [
                        'columns' => [
                            ['label' => 'Expense type', 'type' => 'text'],
                            ['label' => 'Amount', 'type' => 'currency'],
                            ['label' => 'Days', 'type' => 'number'],
                        ],
                    ],
                ],
            ],
            ['form_family' => 'reimbursement'],
        );

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'expense_lines' => json_encode([
                        'rows' => [
                            ['0' => 'per_diem', '1' => '2000', '2' => '1'],
                        ],
                    ], JSON_THROW_ON_ERROR),
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.expense_lines']);
    }

    public function test_open_cash_advance_copies_grids_and_sites_are_searchable(): void
    {
        tenancy()->initialize($this->testTenant);
        Site::query()->create([
            'site_code' => 'NCR-01',
            'name' => 'North Tower',
            'full_address' => '1 Ayala Avenue',
            'status' => 'active',
        ]);
        tenancy()->end();

        $grid = json_encode([
            'rows' => [
                ['0' => '2026-10-13', '1' => 'Taxi'],
            ],
        ], JSON_THROW_ON_ERROR);

        $caFormId = $this->createPublishedForm(
            'Cash advance',
            [
                ['type' => 'currency', 'name' => 'requested_amount', 'label' => 'Requested amount', 'validation' => ['required' => true]],
                ['type' => 'grid', 'name' => 'expense_lines', 'label' => 'Forecast', 'options' => ['columns' => [['label' => 'Date'], ['label' => 'Description']]]],
                ['type' => 'grid', 'name' => 'activity_lines', 'label' => 'Activity', 'options' => ['columns' => [['label' => 'Date'], ['label' => 'Nature']]]],
            ],
            ['form_family' => 'cash_advance'],
        );
        $caId = $this->createSubmission($caFormId, [
            'requested_amount' => '900',
            'expense_lines' => $grid,
            'activity_lines' => $grid,
        ]);
        $this->approveSubmission($caId);

        $liqFormId = $this->createPublishedForm(
            'Liquidation',
            [
                ['type' => 'grid', 'name' => 'expense_lines', 'label' => 'Actual', 'options' => ['columns' => [['label' => 'Date'], ['label' => 'Description']]]],
                ['type' => 'grid', 'name' => 'activity_lines', 'label' => 'Activity', 'options' => ['columns' => [['label' => 'Date'], ['label' => 'Nature']]]],
            ],
            ['form_family' => 'liquidation', 'parent_form_family' => 'cash_advance'],
        );

        $open = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/cash-advances/open?for_form_id={$liqFormId}");

        $open->assertOk();
        $open->assertJsonPath('data.items.0.prefill_values.expense_lines', $grid);
        $open->assertJsonPath('data.items.0.prefill_values.activity_lines', $grid);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/master-data/sites?q=North&limit=10')
            ->assertOk()
            ->assertJsonPath('data.options.0.value', 'NCR-01')
            ->assertJsonPath('data.options.0.data.address', '1 Ayala Avenue');
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<string, mixed>  $metadata
     */
    private function createPublishedForm(
        string $name,
        array $fields,
        array $metadata = [],
        ?string $financeAction = null,
        string $stepLabel = 'Department Head',
    ): string {
        $step = [
            'type' => 'user',
            'approverId' => (string) $this->testTenantAdmin->id,
            'step_order' => 1,
            'label' => $stepLabel,
        ];
        if ($financeAction !== null) {
            $step['condition'] = ['finance_action' => $financeAction];
        }

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => $name,
                'description' => 'Cash advance finance test',
                'status' => 'published',
                'metadata_json' => $metadata,
                'fields' => $fields,
                'steps' => [$step],
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
        $approvalId = $this->pendingApprovalId($submissionId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }

    private function pendingApprovalId(string $submissionId): string
    {
        $inbox = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? null;

        $this->assertNotEmpty($approvalId);

        return (string) $approvalId;
    }
}
