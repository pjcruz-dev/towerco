<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalComment;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalReviewerStepTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $legal;

    private TenantUser $finance;

    private TenantUser $ops;

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
        $this->legal = TenantUser::query()->create([
            'name' => 'Legal Reviewer',
            'email' => 'legal-review@test.localhost',
            'password' => 'password',
        ]);
        $this->legal->assignRole('e_approval_approver');

        $this->finance = TenantUser::query()->create([
            'name' => 'Finance Reviewer',
            'email' => 'finance-review@test.localhost',
            'password' => 'password',
        ]);
        $this->finance->assignRole('e_approval_approver');

        $this->ops = TenantUser::query()->create([
            'name' => 'Ops Approver',
            'email' => 'ops-review@test.localhost',
            'password' => 'password',
        ]);
        $this->ops->assignRole('e_approval_approver');
        tenancy()->end();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_reviewers_acknowledge_before_the_approver_and_a_later_reject_keeps_them(): void
    {
        $formId = $this->createReviewerForm();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'stakeholders' => [
                        (string) $this->legal->id,
                        (string) $this->finance->id,
                    ],
                    'reason' => 'Document control review',
                ],
            ]);

        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with('step')
            ->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(function ($row): bool {
            $condition = is_array($row->step?->condition) ? $row->step->condition : [];

            return (int) $row->step?->step_order === 1 && ($condition['duty'] ?? null) === 'review';
        }));
        $this->assertNull(
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('approver_id', (string) $this->ops->id)
                ->first(),
        );

        DB::connection('tenant')->table('e_approval_settings')->updateOrInsert(
            ['key' => 'manual_follow_up_cooldown_minutes'],
            ['value' => '1'],
        );

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/manual-follow-up")
            ->assertOk()
            ->assertJsonPath('data.notified_approver_count', 2);

        Notification::assertSentTo(
            $this->legal,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $notification): bool => $notification->eventName() === 'manual_follow_up',
        );
        Notification::assertSentTo(
            $this->finance,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $notification): bool => $notification->eventName() === 'manual_follow_up',
        );
        Notification::assertNotSentTo($this->ops, EApprovalSubmissionNotification::class);
        $this->assertTrue(
            EApprovalComment::query()
                ->where('submission_id', $submissionId)
                ->where('message', '[Manual follow-up] Requestor sent a reminder to the current reviewers.')
                ->exists(),
        );

        $legalApproval = $pending->firstWhere('approver_id', (string) $this->legal->id);
        $this->assertNotNull($legalApproval);

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertStatus(422);

        $attachment = EApprovalAttachment::query()->create([
            'submission_id' => $submissionId,
            'field_name' => 'file',
            'file_path' => 'reviews/sample.pdf',
            'file_name' => 'sample.pdf',
        ]);

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/comments", [
                'message' => 'Checked the responsible unit.',
                'attachment_id' => (string) $attachment->id,
                'pin_page' => 1,
                'pin_x' => 0.25,
                'pin_y' => 0.4,
                'mention_user_ids' => [(string) $this->finance->id],
            ])
            ->assertCreated();

        Notification::assertSentTo(
            $this->finance,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $notification): bool => $notification->eventName() === 'comment_mention',
        );

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'acknowledged',
                'remarks' => 'Looks fine.',
            ])
            ->assertStatus(422);

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'acknowledged',
                'remarks' => 'Looks fine.',
                'signature' => 'data:image/png;base64,abc',
                'signature_consent' => true,
                'signature_storage_consent' => true,
            ])
            ->assertOk();

        $this->assertSame(
            'data:image/png;base64,abc',
            EApprovalRequestApproval::query()->findOrFail($legalApproval->id)->signature,
        );

        $this->assertSame('pending', EApprovalSubmission::query()->findOrFail($submissionId)->status);
        $this->assertSame(
            EApprovalApprovalStatus::PENDING,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('approver_id', (string) $this->finance->id)
                ->value('status'),
        );
        $this->assertNull(
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('approver_id', (string) $this->ops->id)
                ->first(),
        );

        $financeApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->finance->id)
            ->firstOrFail();

        $this->actingAs($this->finance, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$financeApproval->id}/decide", [
                'decision' => 'acknowledged',
                'signature' => 'data:image/png;base64,abc',
                'signature_consent' => true,
                'signature_storage_consent' => true,
            ])
            ->assertOk();

        $opsApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->ops->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->first();
        $this->assertNotNull($opsApproval);
        $this->assertSame('pending', EApprovalSubmission::query()->findOrFail($submissionId)->status);

        Carbon::setTestNow(now()->addMinutes(2));

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/manual-follow-up")
            ->assertOk()
            ->assertJsonPath('data.notified_approver_count', 1);

        Notification::assertSentTo(
            $this->ops,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $notification): bool => $notification->eventName() === 'manual_follow_up',
        );
        $this->assertTrue(
            EApprovalComment::query()
                ->where('submission_id', $submissionId)
                ->where('message', '[Manual follow-up] Requestor sent a reminder to the current approver.')
                ->exists(),
        );

        $this->actingAs($this->ops, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$opsApproval->id}/decide", [
                'decision' => 'rejected',
                'remarks' => 'Wrong responsible unit',
            ])
            ->assertOk();

        $this->assertSame('rejected', EApprovalSubmission::query()->findOrFail($submissionId)->status);
        $this->assertSame(
            0,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('status', EApprovalApprovalStatus::INVALIDATED)
                ->count(),
        );
        $this->assertSame(
            2,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('status', EApprovalApprovalStatus::ACKNOWLEDGED)
                ->count(),
        );
        $this->assertTrue(
            EApprovalComment::query()
                ->where('submission_id', $submissionId)
                ->where('attachment_id', $attachment->id)
                ->where('message', 'Checked the responsible unit.')
                ->where('pin_page', 1)
                ->exists(),
        );
    }

    public function test_workflow_preview_marks_reviewer_list_as_review_duty(): void
    {
        $formId = $this->createReviewerForm();

        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", [
                'values' => [
                    'stakeholders' => [
                        (string) $this->legal->id,
                        (string) $this->finance->id,
                    ],
                ],
            ]);

        $preview->assertOk();
        $resolved = $preview->json('data.resolved_steps');
        $this->assertCount(3, $resolved);
        $this->assertSame('review', $resolved[0]['duty']);
        $this->assertSame('review', $resolved[1]['duty']);
        $this->assertSame(1, (int) $resolved[0]['step_order']);
        $this->assertSame(1, (int) $resolved[1]['step_order']);
        $this->assertNull($resolved[2]['duty']);
        $this->assertSame(2, (int) $resolved[2]['step_order']);
    }

    public function test_review_only_chain_completes_when_the_last_reviewer_acknowledges(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Review only chain',
                'description' => 'Fixed reviewers and no approver',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->legal->id,
                        'step_order' => 1,
                        'duty' => 'review',
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->finance->id,
                        'step_order' => 2,
                        'duty' => 'review',
                    ],
                ],
            ]);

        $formRes->assertCreated();
        $formId = (string) $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Review only'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        $legalApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->legal->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->first();
        $this->assertNotNull($legalApproval);
        $this->assertNull(
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('approver_id', (string) $this->finance->id)
                ->first(),
        );

        $signature = [
            'signature' => 'data:image/png;base64,abc',
            'signature_consent' => true,
            'signature_storage_consent' => true,
        ];

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'acknowledged',
                ...$signature,
            ])
            ->assertOk();

        $this->assertSame('pending', EApprovalSubmission::query()->findOrFail($submissionId)->status);

        $financeApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->finance->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->first();
        $this->assertNotNull($financeApproval);

        $this->actingAs($this->finance, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$financeApproval->id}/decide", [
                'decision' => 'acknowledged',
                ...$signature,
            ])
            ->assertOk();

        $this->assertSame('approved', EApprovalSubmission::query()->findOrFail($submissionId)->status);
    }

    private function createReviewerForm(): string
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Document control with reviewers',
                'description' => 'Reviewers comment, then an approver decides',
                'status' => 'published',
                'fields' => [
                    ['type' => 'approver_list', 'name' => 'stakeholders', 'label' => 'Review authorities'],
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    [
                        'type' => 'reviewer_list',
                        'approverId' => 'stakeholders',
                        'step_order' => 1,
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->ops->id,
                        'step_order' => 2,
                    ],
                ],
            ]);

        $formRes->assertCreated();

        return (string) $formRes->json('data.form.id');
    }
}
