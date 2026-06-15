<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalSubmissionDraftTest extends TestCase
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

    public function test_requestor_can_save_draft_and_submit_without_required_fields_until_submit(): void
    {
        $formPayload = [
            'name' => 'Payment Request',
            'description' => 'Draft test',
            'status' => 'published',
            'fields' => [
                ['type' => 'text', 'name' => 'payee', 'label' => 'Payee', 'validation' => ['required' => true]],
            ],
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
            ],
        ];

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', $formPayload);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $draftRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['payee' => ''],
                'as_draft' => true,
            ]);

        $draftRes->assertCreated();
        $this->assertSame(EApprovalSubmissionStatus::DRAFT, $draftRes->json('data.status'));
        $submissionId = $draftRes->json('data.id');
        $this->assertStringStartsWith('DRAFT-', $draftRes->json('data.document_no'));

        $myDraft = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}/my-draft");

        $myDraft->assertOk();
        $this->assertSame($submissionId, $myDraft->json('data.draft.id'));

        $updateRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/draft", [
                'values' => ['payee' => 'Acme Corp'],
            ]);

        $updateRes->assertOk();
        $payeeValue = collect($updateRes->json('data.values'))
            ->first(fn (array $row): bool => ($row['field_name'] ?? null) === 'payee');
        $this->assertSame('Acme Corp', $payeeValue['value'] ?? null);

        $submitFail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/submit", [
                'values' => ['payee' => ''],
            ]);

        $submitFail->assertStatus(422);

        $submitOk = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/submit", [
                'values' => ['payee' => 'Acme Corp'],
            ]);

        $submitOk->assertOk();
        $this->assertSame(EApprovalSubmissionStatus::PENDING, $submitOk->json('data.status'));
        $this->assertStringNotContainsString('DRAFT-', $submitOk->json('data.document_no'));
    }
}
