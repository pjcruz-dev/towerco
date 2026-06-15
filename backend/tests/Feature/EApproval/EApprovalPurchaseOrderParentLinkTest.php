<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalPurchaseOrderParentLinkTest extends TestCase
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

    public function test_purchase_order_links_to_purchase_requisition_parent(): void
    {
        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms();

        $poRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '3200',
                ],
            ]);

        $poRes->assertCreated();
        $poRes->assertJsonPath('data.parent_submission_id', $prSubmissionId);

        $prDocumentNo = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$prSubmissionId}")
            ->json('data.document_no');

        $prRef = collect($poRes->json('data.values'))
            ->firstWhere('field_name', 'purchase_requisition_document_no');

        $this->assertSame($prDocumentNo, $prRef['value'] ?? null);
    }

    public function test_purchase_order_requires_parent_submission(): void
    {
        [, $poFormId] = $this->createApprovedPrAndPoForms();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'values' => [
                    'total_amount' => '1000',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_submission_id']);
    }

    public function test_purchase_order_rejects_pending_purchase_requisition_parent(): void
    {
        $prFormId = $this->createPublishedForm(
            'Purchase requisition',
            [
                ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_requisition'],
        );

        $prSubmissionId = $this->createSubmission($prFormId, [
            'estimated_total' => '5000',
        ]);

        $poFormId = $this->createPublishedForm(
            'Purchase order',
            [
                ['type' => 'currency', 'name' => 'total_amount', 'label' => 'PO total', 'validation' => ['required' => true]],
            ],
            ['form_family' => 'purchase_order', 'parent_form_family' => 'purchase_requisition'],
        );

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '1000',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['parent_submission_id']);
    }

    public function test_purchase_order_rejects_amount_over_open_balance(): void
    {
        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(8000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '9000',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total_amount']);
    }

    public function test_pr_detail_lists_child_purchase_orders_and_budget_summary(): void
    {
        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(15000);

        $firstPo = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '4200',
                ],
            ]);

        $firstPo->assertCreated();
        $firstPoId = (string) $firstPo->json('data.id');

        $secondPo = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '1800',
                ],
            ]);

        $secondPo->assertCreated();
        $secondPoId = (string) $secondPo->json('data.id');

        $prDetail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$prSubmissionId}");

        $prDetail->assertOk();
        $prDetail->assertJsonPath('data.related_submissions.context_form_family', 'purchase_requisition');
        $prDetail->assertJsonPath('data.related_submissions.summary.kind', 'purchase_requisition_budget');
        $prDetail->assertJsonPath('data.related_submissions.summary.total_amount', 15000);
        $prDetail->assertJsonPath('data.related_submissions.summary.committed_amount', 6000);
        $prDetail->assertJsonPath('data.related_submissions.summary.open_balance', 9000);
        $prDetail->assertJsonPath('data.related_submissions.children.0.id', $secondPoId);
        $prDetail->assertJsonPath('data.related_submissions.children.0.form_family', 'purchase_order');
        $prDetail->assertJsonPath('data.related_submissions.children.0.amount_value', '1800');
        $prDetail->assertJsonPath('data.related_submissions.children.1.id', $firstPoId);
        $prDetail->assertJsonPath('data.related_submissions.children.1.amount_value', '4200');

        $poDetail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$firstPoId}");

        $poDetail->assertOk();
        $poDetail->assertJsonPath('data.related_submissions.parent.id', $prSubmissionId);
        $poDetail->assertJsonPath('data.related_submissions.parent.form_family', 'purchase_requisition');
        $poDetail->assertJsonPath('data.related_submissions.parent.amount_value', '15000');
    }

    public function test_purchase_order_respects_cumulative_open_balance(): void
    {
        [$prSubmissionId, $poFormId] = $this->createApprovedPrAndPoForms(10000);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '4000',
                ],
            ])
            ->assertCreated();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $poFormId,
                'parent_submission_id' => $prSubmissionId,
                'values' => [
                    'total_amount' => '7000',
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['total_amount']);
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
                ['type' => 'text', 'name' => 'purchase_requisition_document_no', 'label' => 'PR document no.', 'validation' => ['required' => true]],
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
                'description' => 'PO parent link test',
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
