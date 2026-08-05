<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

/**
 * Regression: mid-band conditional path must advance on compiled steps only.
 * Matching live template step_order incorrectly skipped shared parallel bands.
 */
final class EApprovalCompiledWorkflowAdvanceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $lowApprover;

    private TenantUser $midApprover;

    private TenantUser $highApprover;

    private TenantUser $listA;

    private TenantUser $listB;

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
        $this->lowApprover = $this->makeApprover('Low Band', 'low.band@test.localhost');
        $this->midApprover = $this->makeApprover('Mid Band', 'mid.band@test.localhost');
        $this->highApprover = $this->makeApprover('High Band', 'high.band@test.localhost');
        $this->listA = $this->makeApprover('List A', 'list.a@test.localhost');
        $this->listB = $this->makeApprover('List B', 'list.b@test.localhost');
        tenancy()->end();
    }

    public function test_mid_band_advances_through_compiled_parallel_and_list_not_live_template(): void
    {
        $formId = $this->createAmountLadderForm();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'Mid band payment',
                    'non_po' => '12000',
                    'approver_list' => json_encode([
                        (string) $this->listA->id,
                        (string) $this->listB->id,
                        (string) $this->midApprover->id,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $firstPending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with('step')
            ->get();
        $this->assertCount(1, $firstPending);
        $this->assertSame($submissionId, (string) $firstPending->first()?->step?->compiled_for_submission_id);

        $compiledCount = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->count();
        $this->assertSame(8, $compiledCount, 'Expected compacted mid-band compiled steps');

        // Step 1 — mid ladder (>5000)
        $this->assertPendingApprovers($submissionId, 1, [(string) $this->midApprover->id]);
        $this->approvePendingFor($submissionId, (string) $this->midApprover->id);

        // Step 2 — mid exclusive band (>5000 and <=20000)
        $this->assertPendingApprovers($submissionId, 2, [(string) $this->midApprover->id]);
        $this->assertApprovalsUseCompiledStepsOnly($submissionId);
        $this->approvePendingFor($submissionId, (string) $this->midApprover->id);

        // Step 3 — always-on parallel (all)
        $this->assertPendingApprovers($submissionId, 3, [
            (string) $this->lowApprover->id,
            (string) $this->midApprover->id,
        ]);
        $this->assertApprovalsUseCompiledStepsOnly($submissionId);
        $this->approvePendingFor($submissionId, (string) $this->lowApprover->id);
        $this->assertSame('pending', EApprovalSubmission::query()->findOrFail($submissionId)->status);
        $this->approvePendingFor($submissionId, (string) $this->midApprover->id);

        // Step 4 — user list (any)
        $pendingList = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with('step')
            ->get();
        $this->assertCount(3, $pendingList);
        $this->assertTrue($pendingList->every(static fn ($row) => (int) $row->step?->step_order === 4));
        $this->assertApprovalsUseCompiledStepsOnly($submissionId);

        $listApproval = $pendingList->firstWhere('approver_id', (string) $this->listA->id);
        $this->assertNotNull($listApproval);
        $this->actingAs($this->listA, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$listApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        // Step 5 — final always-on
        $this->assertPendingApprovers($submissionId, 5, [(string) $this->lowApprover->id]);
        $this->assertApprovalsUseCompiledStepsOnly($submissionId);
        $this->approvePendingFor($submissionId, (string) $this->lowApprover->id);

        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertSame('approved', $submission->status);
        $this->assertApprovalsUseCompiledStepsOnly($submissionId);

        $liveLinked = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->whereHas('step', static fn ($q) => $q->whereNull('compiled_for_submission_id'))
            ->count();
        $this->assertSame(0, $liveLinked, 'No approval may attach to live template steps');
    }

    public function test_full_restart_resubmit_advances_past_orphan_compiled_steps(): void
    {
        $formId = $this->createAmountLadderForm();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'Restart path',
                    'non_po' => '12000',
                    'approver_list' => json_encode([
                        (string) $this->listA->id,
                        (string) $this->listB->id,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]);
        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $this->approvePendingFor($submissionId, (string) $this->midApprover->id); // step 1
        $this->approvePendingFor($submissionId, (string) $this->midApprover->id); // step 2
        $this->assertPendingApprovers($submissionId, 3, [
            (string) $this->lowApprover->id,
            (string) $this->midApprover->id,
        ]);

        $compiledBefore = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->count();
        $this->assertGreaterThan(0, $compiledBefore);

        $revision = $this->actingAs($this->lowApprover, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/revision", [
                'remarks' => 'Please revise amount details.',
            ]);
        $revision->assertOk();

        $resubmit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => [
                    'title' => 'Restart path v2',
                    'non_po' => '12000',
                    'approver_list' => json_encode([
                        (string) $this->listA->id,
                        (string) $this->listB->id,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]);
        $resubmit->assertOk();

        $compiledAfter = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->count();
        $this->assertGreaterThan($compiledBefore, $compiledAfter, 'Resubmit should retain prior compiled steps for history');

        $this->approvePendingFor($submissionId, (string) $this->midApprover->id); // step 1 again
        $this->approvePendingFor($submissionId, (string) $this->midApprover->id); // step 2 again

        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertSame(3, (int) $submission->current_step, 'Must advance to parallel step 3, not stall on orphan step 2');
        $this->assertPendingApprovers($submissionId, 3, [
            (string) $this->lowApprover->id,
            (string) $this->midApprover->id,
        ]);
    }

    /**
     * @param  list<string>  $approverIds
     */
    private function assertPendingApprovers(string $submissionId, int $stepOrder, array $approverIds): void
    {
        $cycle = max(1, (int) (EApprovalSubmission::query()->findOrFail($submissionId)->approval_cycle ?: 1));
        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->where(function ($query) use ($cycle): void {
                $query->where('approval_cycle', $cycle)->orWhereNull('approval_cycle');
            })
            ->with('step')
            ->get();

        $this->assertTrue($pending->every(static fn ($row) => (int) $row->step?->step_order === $stepOrder));
        $this->assertEqualsCanonicalizing(
            $approverIds,
            $pending->pluck('approver_id')->map(static fn ($id) => (string) $id)->all(),
        );
    }

    private function assertApprovalsUseCompiledStepsOnly(string $submissionId): void
    {
        $rows = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->with('step')
            ->get();

        foreach ($rows as $row) {
            $this->assertNotNull($row->step?->compiled_for_submission_id);
            $this->assertSame($submissionId, (string) $row->step->compiled_for_submission_id);
        }
    }

    private function approvePendingFor(string $submissionId, string $approverId): void
    {
        $approval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', $approverId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->firstOrFail();

        $user = TenantUser::query()->findOrFail($approverId);

        $this->actingAs($user, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }

    private function makeApprover(string $name, string $email): TenantUser
    {
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('e_approval_approver');

        return $user;
    }

    private function createAmountLadderForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Amount ladder compiled advance',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'currency', 'name' => 'non_po', 'label' => 'Non-PO amount'],
                    ['type' => 'approver_list', 'name' => 'approver_list', 'label' => 'Approver list'],
                ],
                'steps' => [
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->lowApprover->id,
                        'step_order' => 1,
                        'when' => [['field' => 'non_po', 'operator' => 'lte', 'value' => '5000']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->midApprover->id,
                        'step_order' => 2,
                        'when' => [['field' => 'non_po', 'operator' => 'gt', 'value' => '5000']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->lowApprover->id,
                        'step_order' => 3,
                        'when' => [['field' => 'non_po', 'operator' => 'lte', 'value' => '5000']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->midApprover->id,
                        'step_order' => 4,
                        'when' => [
                            ['field' => 'non_po', 'operator' => 'gt', 'value' => '5000'],
                            ['field' => 'non_po', 'operator' => 'lte', 'value' => '20000'],
                        ],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->highApprover->id,
                        'step_order' => 5,
                        'when' => [['field' => 'non_po', 'operator' => 'gt', 'value' => '20000']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->lowApprover->id,
                        'step_order' => 6,
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->midApprover->id,
                        'step_order' => 6,
                    ],
                    [
                        'type' => 'user_list',
                        'approverId' => 'approver_list',
                        'step_order' => 8,
                        'parallel_mode' => 'any',
                        'fallback_approver_id' => (string) $this->lowApprover->id,
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->lowApprover->id,
                        'step_order' => 9,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }
}
