<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalExternalDownloadToken;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormOutboundFile;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Notifications\EApprovalExternalSubmissionNotification;
use App\Modules\EApproval\Services\EApprovalSettingsService;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalExternalMailEvent;
use App\Modules\EApproval\Support\EApprovalRevisionRouting;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalExternalClosedLoopTest extends TestCase
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

        Mail::fake();
        Notification::fake();
        Http::fake();

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        $this->approver = TenantUser::query()->create([
            'name' => 'External Loop Approver',
            'email' => 'external-loop-approver@test.localhost',
            'password' => 'password',
        ]);
        $this->approver->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_default_settings_do_not_email_external_or_post_teams(): void
    {
        $token = $this->createPublicLink($this->createPublishedForm());

        $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Site access'],
            ])
            ->assertCreated();

        Notification::assertNotSentTo(
            Notification::route('mail', 'vendor@example.com'),
            EApprovalExternalSubmissionNotification::class,
        );

        Http::assertNothingSent();
    }

    public function test_external_received_email_and_teams_when_enabled(): void
    {
        tenancy()->initialize($this->testTenant);
        $settings = app(EApprovalSettingsService::class);
        $settings->setString(EApprovalSettingsService::NOTIFY_EXTERNAL_ON_RECEIVED, 'true');
        $settings->setString(EApprovalSettingsService::NOTIFY_TEAMS_ON_EXTERNAL_SUBMIT, 'true');
        $settings->setString(EApprovalSettingsService::TEAMS_WEBHOOK_URL, 'https://example.test/teams-webhook');
        tenancy()->end();

        $token = $this->createPublicLink($this->createPublishedForm());

        $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Site access'],
            ])
            ->assertCreated();

        Notification::assertSentOnDemand(
            EApprovalExternalSubmissionNotification::class,
            static function (EApprovalExternalSubmissionNotification $notification): bool {
                return $notification->eventName() === EApprovalExternalMailEvent::RECEIVED;
            },
        );

        Http::assertSent(static fn ($request): bool => $request->url() === 'https://example.test/teams-webhook');
    }

    public function test_return_resubmit_and_approve_package_flow(): void
    {
        Storage::fake((string) config('toweros.tenant_files.disk', 'local'));

        $this->testTenant->plan_tier = 'enterprise';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        $settings = app(EApprovalSettingsService::class);
        $settings->setString(EApprovalSettingsService::NOTIFY_EXTERNAL_ON_RETURNED, 'true');
        $settings->setString(EApprovalSettingsService::NOTIFY_EXTERNAL_ON_APPROVED, 'true');
        $settings->setString(EApprovalSettingsService::NOTIFY_EXTERNAL_ON_REJECTED, 'true');
        tenancy()->end();

        $formId = $this->createPublishedFormWithFileAndResume();
        $this->uploadOutboundDeliverable($formId, 'approved-pack.pdf');
        $token = $this->createPublicLink($formId);

        $submit = $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Initial reason'],
            ])
            ->assertCreated();

        $submissionId = (string) $submit->json('data.submission_id');

        $approvalId = $this->pendingApprovalId($submissionId);
        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/revision", [
                'remarks' => 'Please revise the reason field.',
            ])
            ->assertOk();

        Notification::assertSentOnDemand(
            EApprovalExternalSubmissionNotification::class,
            static function (EApprovalExternalSubmissionNotification $notification): bool {
                return $notification->eventName() === EApprovalExternalMailEvent::RETURNED;
            },
        );

        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertNotNull($submission->external_resubmit_token_hash);
        $plainResubmit = 'test-resubmit-token-plain';
        $submission->external_resubmit_token_hash = hash('sha256', $plainResubmit);
        $submission->external_resubmit_token_expires_at = now()->addDay();
        $submission->save();
        tenancy()->end();

        $reviseShow = $this->withHeaders($this->publicApiHeaders())
            ->getJson('/api/v1/public/e-approval/submissions/'.$submissionId.'/revise?resubmit_token='.$plainResubmit);

        $reviseShow->assertOk()
            ->assertJsonPath('data.document_no', $submit->json('data.document_no'))
            ->assertJsonPath('data.values.reason', 'Initial reason');

        $resubmit = $this->withHeaders($this->publicApiHeaders())
            ->putJson('/api/v1/public/e-approval/submissions/'.$submissionId.'/resubmit', [
                'resubmit_token' => $plainResubmit,
                'values' => ['reason' => 'Revised reason'],
            ]);

        $resubmit->assertOk()->assertJsonPath('data.submission_id', $submissionId);

        tenancy()->initialize($this->testTenant);
        $afterResubmit = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertSame('pending', $afterResubmit->status);
        $this->assertSame(EApprovalRevisionRouting::RESUME_RETURNING_STEP, $afterResubmit->last_revision_routing);
        $this->assertNull($afterResubmit->external_resubmit_token_hash);
        tenancy()->end();

        $approvalId = $this->pendingApprovalId($submissionId);
        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        Notification::assertSentOnDemand(
            EApprovalExternalSubmissionNotification::class,
            static function (EApprovalExternalSubmissionNotification $notification): bool {
                return $notification->eventName() === EApprovalExternalMailEvent::APPROVED;
            },
        );

        tenancy()->initialize($this->testTenant);
        $downloadToken = EApprovalExternalDownloadToken::query()->where('submission_id', $submissionId)->first();
        $this->assertNotNull($downloadToken);
        $this->assertNotNull($downloadToken->form_outbound_file_id);
        $outbound = EApprovalFormOutboundFile::query()->where('form_id', $formId)->first();
        $this->assertNotNull($outbound);
        $this->assertSame((string) $outbound->id, (string) $downloadToken->form_outbound_file_id);
        $plainDownload = 'package-download-token';
        $downloadToken->token_hash = hash('sha256', $plainDownload);
        $downloadToken->expires_at = now()->addDay();
        $downloadToken->save();
        tenancy()->end();

        $this->withHeaders($this->publicApiHeaders())
            ->get('/api/v1/public/e-approval/package-downloads/'.$plainDownload)
            ->assertOk();

        $this->withHeaders($this->publicApiHeaders())
            ->get('/api/v1/public/e-approval/package-downloads/bad-token')
            ->assertStatus(422);
    }

    public function test_reject_emails_external_when_enabled(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalSettingsService::class)->setString(EApprovalSettingsService::NOTIFY_EXTERNAL_ON_REJECTED, 'true');
        tenancy()->end();

        $token = $this->createPublicLink($this->createPublishedForm());
        $submissionId = (string) $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Site access'],
            ])
            ->json('data.submission_id');

        $approvalId = $this->pendingApprovalId($submissionId);
        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'rejected',
                'remarks' => 'Incomplete documentation provided.',
            ])
            ->assertOk();

        Notification::assertSentOnDemand(
            EApprovalExternalSubmissionNotification::class,
            static function (EApprovalExternalSubmissionNotification $notification): bool {
                return $notification->eventName() === EApprovalExternalMailEvent::REJECTED;
            },
        );
    }

    /**
     * @return array<string, string>
     */
    private function publicApiHeaders(): array
    {
        return [
            'X-Tenant-Domain' => 'test.localhost',
        ];
    }

    private function createPublishedForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Vendor intake',
                'description' => 'External vendors',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPublishedFormWithFileAndResume(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Vendor deliverable',
                'description' => 'External with package',
                'status' => 'published',
                'metadata_json' => [
                    'revision' => [
                        'routing' => EApprovalRevisionRouting::RESUME_RETURNING_STEP,
                        'material_fields' => [],
                        'approver_can_force_full_restart' => false,
                    ],
                    'outbound' => [
                        'email_package_on_approve' => true,
                    ],
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();
        $formId = (string) $response->json('data.form.id');

        // Ensure metadata persisted for package config (API may nest differently).
        tenancy()->initialize($this->testTenant);
        $form = EApprovalForm::query()->findOrFail($formId);
        $meta = is_array($form->metadata_json) ? $form->metadata_json : [];
        $meta['revision'] = [
            'routing' => EApprovalRevisionRouting::RESUME_RETURNING_STEP,
            'material_fields' => [],
            'approver_can_force_full_restart' => false,
        ];
        $meta['outbound'] = [
            'email_package_on_approve' => true,
        ];
        $form->metadata_json = $meta;
        $form->save();
        tenancy()->end();

        return $formId;
    }

    private function uploadOutboundDeliverable(string $formId, string $fileName): void
    {
        $file = UploadedFile::fake()->create($fileName, 120, 'application/pdf');
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/e-approval/forms/{$formId}/outbound-files", [
                'file' => $file,
            ])
            ->assertCreated()
            ->assertJsonPath('data.file_name', $fileName);
    }

    private function createPublicLink(string $formId): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/public-links", [
                'label' => 'Vendor portal',
                'sponsor_user_id' => (string) $this->testTenantAdmin->id,
            ]);

        $response->assertCreated();

        return (string) $response->json('data.token');
    }

    private function pendingApprovalId(string $submissionId): string
    {
        tenancy()->initialize($this->testTenant);
        $id = (string) EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->value('id');
        tenancy()->end();

        $this->assertNotSame('', $id);

        return $id;
    }
}
