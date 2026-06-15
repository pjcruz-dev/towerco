<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFormVersioningTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $approver;

    private TenantUser $approver2;

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
            'name' => 'Approver One',
            'email' => 'approver1@test.localhost',
            'password' => 'password',
        ]);
        $this->approver->assignRole('e_approval_approver');
        $this->approver2 = TenantUser::query()->create([
            'name' => 'Approver Two',
            'email' => 'approver2@test.localhost',
            'password' => 'password',
        ]);
        $this->approver2->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_structural_update_blocked_without_confirm_when_open_submissions_exist(): void
    {
        $formId = $this->createPublishedForm([
            ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
        ]);

        $this->createSubmission($formId);

        $blocked = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/forms/{$formId}", $this->formPayload([
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ['type' => 'user', 'approverId' => (string) $this->approver2->id, 'step_order' => 2],
            ],
        ]));

        $blocked->assertUnprocessable();
        $blocked->assertJsonValidationErrors(['confirm_form_upgrade']);
    }

    public function test_structural_update_with_confirm_preserves_in_flight_approval(): void
    {
        $formId = $this->createPublishedForm([
            ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
        ]);

        $submissionId = $this->createSubmission($formId);
        $approvalId = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->value('id');
        $this->assertNotEmpty($approvalId);

        $updated = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/forms/{$formId}", $this->formPayload([
            'confirm_form_upgrade' => true,
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ['type' => 'user', 'approverId' => (string) $this->approver2->id, 'step_order' => 2],
            ],
        ]));

        $updated->assertOk();
        $this->assertNotNull(EApprovalRequestApproval::query()->find($approvalId));
    }

    public function test_retired_form_rejects_new_submissions(): void
    {
        $formId = $this->createPublishedForm([
            ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
        ]);

        $retire = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/forms/{$formId}", $this->formPayload([
            'accepts_new_submissions' => false,
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
            ],
        ]));

        $retire->assertOk();

        $sub = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Should fail'],
            ]);

        $sub->assertUnprocessable();
        $sub->assertJsonValidationErrors(['form_id']);
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     */
    private function createPublishedForm(array $steps): string
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', $this->formPayload([
            'steps' => $steps,
        ]));

        $res->assertCreated();

        return (string) $res->json('data.form.id');
    }

    private function createSubmission(string $formId): string
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Pending request'],
            ]);

        $res->assertCreated();

        $submission = EApprovalSubmission::query()->find($res->json('data.id'));
        $this->assertNotNull($submission);
        $this->assertSame('pending', $submission->status);

        return (string) $submission->id;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Leave Request',
            'description' => 'Test form',
            'status' => 'published',
            'fields' => [
                ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
            ],
            'steps' => [],
        ], $overrides);
    }
}
