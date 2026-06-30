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
}
