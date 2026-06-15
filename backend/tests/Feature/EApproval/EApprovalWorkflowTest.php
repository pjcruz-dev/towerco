<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalWorkflowTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $approver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        $this->approver = TenantUser::query()->create([
            'name' => 'Approver User',
            'email' => 'approver@test.localhost',
            'password' => 'password',
        ]);
        $this->approver->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_form_submission_and_approval_happy_path(): void
    {
        $formPayload = [
            'name' => 'Leave Request',
            'description' => 'Test form',
            'status' => 'published',
            'fields' => [
                ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
            ],
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
            ],
        ];

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', $formPayload);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Annual leave'],
            ]);

        $subRes->assertCreated();
        $submissionId = $subRes->json('data.id');

        $inbox = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();
        $approvalId = $inbox->json('data.0.id');
        $this->assertNotEmpty($approvalId);

        $decide = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ]);

        $decide->assertOk();

        $submission = EApprovalSubmission::query()->find($submissionId);
        $this->assertNotNull($submission);
        $this->assertSame('approved', $submission->status);
    }
}
