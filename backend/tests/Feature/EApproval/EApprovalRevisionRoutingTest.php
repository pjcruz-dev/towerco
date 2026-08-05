<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalRevisionRouting;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalRevisionRoutingTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $approverOne;

    private TenantUser $approverTwo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        Mail::fake();
        Notification::fake();

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        $this->approverOne = TenantUser::query()->create([
            'name' => 'Approver One',
            'email' => 'approver1-revision@test.localhost',
            'password' => 'password',
        ]);
        $this->approverOne->assignRole('e_approval_approver');

        $this->approverTwo = TenantUser::query()->create([
            'name' => 'Approver Two',
            'email' => 'approver2-revision@test.localhost',
            'password' => 'password',
        ]);
        $this->approverTwo->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_returned_step_preview_shows_returned_not_not_needed(): void
    {
        [, $submissionId] = $this->createTwoStepSubmission();

        $this->approvePendingAs($this->approverOne);
        $this->requestRevisionAs($this->approverTwo, $submissionId, 'Please fix the reason text.');

        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview");

        $preview->assertOk();
        $steps = collect($preview->json('data.resolved_steps'));
        $this->assertSame('returned', $steps->firstWhere('step_order', 2)['runtime_status'] ?? null);
        $this->assertSame('approved', $steps->firstWhere('step_order', 1)['runtime_status'] ?? null);
    }

    public function test_requestor_can_cancel_returned_submission(): void
    {
        [, $submissionId] = $this->createTwoStepSubmission();

        $this->approvePendingAs($this->approverOne);
        $this->requestRevisionAs($this->approverTwo, $submissionId, 'Please fix the reason text.');

        $cancel = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/cancel");

        $cancel->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('cancelled', EApprovalSubmission::query()->findOrFail($submissionId)->status);
    }

    public function test_default_resubmit_restarts_from_step_one(): void
    {
        [$formId, $submissionId] = $this->createTwoStepSubmission();

        $this->approvePendingAs($this->approverOne);
        $this->requestRevisionAs($this->approverTwo, $submissionId, 'Please fix the reason text.');

        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertSame(2, (int) $submission->returned_from_step);
        $this->assertSame(
            EApprovalApprovalStatus::APPROVED,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->whereHas('step', static fn ($q) => $q->where('step_order', 1))
                ->value('status'),
        );

        $resubmit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => ['reason' => 'Updated reason', 'amount' => '100'],
            ]);

        $resubmit->assertOk();
        $this->assertSame(EApprovalRevisionRouting::RESTART_FROM_START, $resubmit->json('data.last_revision_routing'));
        $this->assertSame(1, (int) $resubmit->json('data.current_step'));

        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->get();
        $this->assertCount(1, $pending);
        $this->assertSame(1, (int) $pending->first()->step?->step_order);
    }

    public function test_resume_routing_returns_to_returning_step(): void
    {
        [$formId, $submissionId] = $this->createTwoStepSubmission([
            'revision' => [
                'routing' => EApprovalRevisionRouting::RESUME_RETURNING_STEP,
                'material_fields' => ['amount'],
                'approver_can_force_full_restart' => true,
            ],
        ]);

        $this->approvePendingAs($this->approverOne);
        $this->requestRevisionAs($this->approverTwo, $submissionId, 'Please clarify the reason.');

        $resubmit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => ['reason' => 'Clarified reason', 'amount' => '100'],
            ]);

        $resubmit->assertOk();
        $this->assertSame(EApprovalRevisionRouting::RESUME_RETURNING_STEP, $resubmit->json('data.last_revision_routing'));
        $this->assertSame(2, (int) $resubmit->json('data.current_step'));

        $approvedStepOne = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::APPROVED)
            ->whereHas('step', static fn ($q) => $q->where('step_order', 1))
            ->exists();
        $this->assertTrue($approvedStepOne);

        $pendingStep = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with('step')
            ->first();
        $this->assertNotNull($pendingStep);
        $this->assertSame(2, (int) $pendingStep->step?->step_order);

        // Path must keep earlier approvals visible after resume recompiles new step IDs.
        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview");
        $preview->assertOk();
        $steps = collect($preview->json('data.resolved_steps'));
        $this->assertSame('approved', $steps->firstWhere('step_order', 1)['runtime_status'] ?? null);
        $this->assertSame('pending', $steps->firstWhere('step_order', 2)['runtime_status'] ?? null);
    }

    public function test_material_field_change_forces_restart_when_resume_enabled(): void
    {
        [$formId, $submissionId] = $this->createTwoStepSubmission([
            'revision' => [
                'routing' => EApprovalRevisionRouting::RESUME_RETURNING_STEP,
                'material_fields' => ['amount'],
                'approver_can_force_full_restart' => true,
            ],
        ]);

        $this->approvePendingAs($this->approverOne);
        $this->requestRevisionAs($this->approverTwo, $submissionId, 'Amount looks wrong.');

        $resubmit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => ['reason' => 'Annual leave', 'amount' => '250'],
            ]);

        $resubmit->assertOk();
        $this->assertSame(EApprovalRevisionRouting::RESTART_FROM_START, $resubmit->json('data.last_revision_routing'));
        $this->assertSame(
            EApprovalRevisionRouting::REASON_MATERIAL_FIELDS,
            $resubmit->json('data.last_revision_routing_reason'),
        );
        $this->assertSame(1, (int) $resubmit->json('data.current_step'));
    }

    public function test_approver_force_full_restart_overrides_resume(): void
    {
        [$formId, $submissionId] = $this->createTwoStepSubmission([
            'revision' => [
                'routing' => EApprovalRevisionRouting::RESUME_RETURNING_STEP,
                'material_fields' => [],
                'approver_can_force_full_restart' => true,
            ],
        ]);

        $this->approvePendingAs($this->approverOne);
        $this->requestRevisionAs(
            $this->approverTwo,
            $submissionId,
            'Please revise thoroughly.',
            forceFullRestart: true,
        );

        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertTrue((bool) $submission->force_full_restart);

        $resubmit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => ['reason' => 'Thoroughly revised', 'amount' => '100'],
            ]);

        $resubmit->assertOk();
        $this->assertSame(EApprovalRevisionRouting::RESTART_FROM_START, $resubmit->json('data.last_revision_routing'));
        $this->assertSame(
            EApprovalRevisionRouting::REASON_FORCE_FLAG,
            $resubmit->json('data.last_revision_routing_reason'),
        );
        $this->assertSame(1, (int) $resubmit->json('data.current_step'));
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{0: string, 1: string}
     */
    private function createTwoStepSubmission(array $metadata = []): array
    {
        $formPayload = [
            'name' => 'Two-step revision form',
            'description' => 'Revision routing test',
            'status' => 'published',
            'metadata_json' => $metadata,
            'fields' => [
                ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ['type' => 'text', 'name' => 'amount', 'label' => 'Amount'],
            ],
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->approverOne->id, 'step_order' => 1],
                ['type' => 'user', 'approverId' => (string) $this->approverTwo->id, 'step_order' => 2],
            ],
        ];

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', $formPayload);
        $formRes->assertCreated();
        $formId = (string) $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Annual leave', 'amount' => '100'],
            ]);
        $subRes->assertCreated();

        return [$formId, (string) $subRes->json('data.id')];
    }

    private function approvePendingAs(TenantUser $approver): void
    {
        $inbox = $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $inbox->assertOk();
        $approvalId = $inbox->json('data.0.id');
        $this->assertNotEmpty($approvalId);

        $decide = $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ]);
        $decide->assertOk();
    }

    private function requestRevisionAs(
        TenantUser $approver,
        string $submissionId,
        string $remarks,
        bool $forceFullRestart = false,
    ): void {
        $inbox = $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $inbox->assertOk();
        $this->assertNotEmpty($inbox->json('data.0.id'));

        $revision = $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/revision", [
                'remarks' => $remarks,
                'force_full_restart' => $forceFullRestart,
            ]);
        $revision->assertOk();
        $this->assertSame('returned', $revision->json('data.status'));
    }
}
