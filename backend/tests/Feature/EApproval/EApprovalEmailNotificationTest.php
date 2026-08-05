<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Notifications\EApprovalMailTestNotification;
use App\Modules\EApproval\Notifications\EApprovalSubmissionNotification;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalEmailNotificationTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $approver;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'toweros.notifications_mail_mailer' => 'array',
            'mail.default' => 'array',
            'cache.default' => 'array',
        ]);

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

    public function test_submission_notifies_requestor_and_approver_by_mail(): void
    {
        Notification::fake();

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Mail Test Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Test'],
            ]);

        $subRes->assertCreated();

        Notification::assertSentTo($this->testTenantAdmin, EApprovalSubmissionNotification::class);
        Notification::assertSentTo($this->approver, EApprovalSubmissionNotification::class);
    }

    public function test_manual_follow_up_notifies_current_approver_by_mail(): void
    {
        Notification::fake();

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Follow-up Mail Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Needs approval'],
            ]);

        $subRes->assertCreated();
        $submissionId = $subRes->json('data.id');

        Notification::fake();

        $followUpRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/manual-follow-up", [
                'note' => 'Please review today',
            ]);

        $followUpRes->assertOk();

        Notification::assertSentTo($this->approver, EApprovalSubmissionNotification::class);
    }

    public function test_cancel_notifies_requestor_and_pending_approver_by_mail(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Cancel Mail Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Will cancel'],
            ]);

        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        Notification::fake();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        Notification::assertSentTo(
            $this->approver,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $notification): bool => $notification->eventName() === 'cancelled',
        );
        Notification::assertSentTo(
            $this->testTenantAdmin,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $notification): bool => $notification->eventName() === 'cancelled',
        );
    }

    public function test_settings_test_email_endpoint_sends_modern_test_notification(): void
    {
        Notification::fake();

        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/settings/test-email');

        $res->assertOk();
        $res->assertJsonPath('data.mailer', 'array');

        Notification::assertSentTo($this->testTenantAdmin, EApprovalMailTestNotification::class);
    }

    public function test_settings_test_email_rejects_log_mailer(): void
    {
        config([
            'toweros.notifications_mail_mailer' => 'log',
            'mail.default' => 'log',
            'cache.default' => 'array',
        ]);

        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/settings/test-email');

        $res->assertUnprocessable();
    }

    public function test_returned_and_restart_resubmit_use_revision_aware_mail_events(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Revision Mail Restart Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);
        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Initial'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        Notification::fake();

        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1')
            ->assertOk();

        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/revision", [
                'remarks' => 'Please revise the reason field.',
            ])
            ->assertOk();

        Notification::assertSentTo(
            $this->testTenantAdmin,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'returned',
        );

        Notification::fake();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => ['reason' => 'Revised'],
            ])
            ->assertOk();

        Notification::assertSentTo(
            $this->testTenantAdmin,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'resubmitted_restart',
        );
        Notification::assertSentTo(
            $this->approver,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'approval_assigned_revised',
        );
    }

    public function test_resume_resubmit_uses_revision_aware_mail_events(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Revision Mail Resume Form',
                'status' => 'published',
                'metadata_json' => [
                    'revision' => [
                        'routing' => 'resume_returning_step',
                        'material_fields' => [],
                        'approver_can_force_full_restart' => false,
                    ],
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->approver->id,
                        'step_order' => 2,
                    ],
                ],
            ]);
        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Initial'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');

        $inbox = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $approvalId = $inbox->json('data.0.id');
        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/revision", [
                'remarks' => 'Please clarify step two details.',
            ])
            ->assertOk();

        Notification::fake();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/submissions/{$submissionId}/resubmit", [
                'values' => ['reason' => 'Clarified'],
            ])
            ->assertOk();

        Notification::assertSentTo(
            $this->testTenantAdmin,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'resubmitted_resume',
        );
        Notification::assertSentTo(
            $this->approver,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'approval_assigned_revised',
        );
    }

    public function test_reroute_emails_previous_approver(): void
    {
        $alternate = null;
        tenancy()->initialize($this->testTenant);
        $alternate = TenantUser::query()->create([
            'name' => 'Alternate Approver',
            'email' => 'alternate-mail@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $alternate->assignRole('e_approval_approver');
        tenancy()->end();

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Reroute Mail Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);
        $formRes->assertCreated();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formRes->json('data.form.id'),
                'values' => ['reason' => 'Reroute me'],
            ]);
        $subRes->assertCreated();

        $inbox = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $approvalId = $inbox->json('data.0.id');
        $this->assertNotEmpty($approvalId);

        Notification::fake();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/reroute", [
                'new_approver_id' => (string) $alternate->id,
                'reason' => 'Original approver is out of office',
            ])
            ->assertOk();

        Notification::assertSentTo(
            $this->approver,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'approval_rerouted',
        );
        Notification::assertSentTo(
            $alternate,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'approval_assigned',
        );
    }

    public function test_exclusive_path_skip_notifies_requestor_by_mail(): void
    {
        $highApprover = null;
        tenancy()->initialize($this->testTenant);
        $highApprover = TenantUser::query()->create([
            'name' => 'High Band Approver',
            'email' => 'high-band-mail@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $highApprover->assignRole('e_approval_approver');
        tenancy()->end();

        Notification::fake();

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Exclusive Skip Mail Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'number', 'name' => 'amount', 'label' => 'Amount'],
                ],
                'steps' => [
                    [
                        'type' => 'user',
                        'approverId' => (string) $this->approver->id,
                        'step_order' => 1,
                        'when' => [['field' => 'amount', 'operator' => 'lte', 'value' => '100']],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $highApprover->id,
                        'step_order' => 2,
                        'when' => [['field' => 'amount', 'operator' => 'gt', 'value' => '100']],
                    ],
                ],
            ]);
        $formRes->assertCreated();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formRes->json('data.form.id'),
                'values' => ['amount' => '250'],
            ]);
        $subRes->assertCreated();

        Notification::assertSentTo(
            $this->testTenantAdmin,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'workflow_steps_skipped',
        );
        Notification::assertSentTo(
            $highApprover,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'approval_assigned',
        );
        Notification::assertNotSentTo(
            $this->approver,
            EApprovalSubmissionNotification::class,
            static fn (EApprovalSubmissionNotification $n): bool => $n->eventName() === 'approval_assigned',
        );
    }
}
