<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmissionShareLink;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalSubmissionShareLinkTest extends TestCase
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

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        $this->approver = TenantUser::query()->create([
            'name' => 'Share Approver',
            'email' => 'share-approver@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->approver->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_share_link_only_for_approved_and_public_payload_works(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Share Form',
                'description' => 'Share test',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Title'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['title' => 'Shared doc'],
            ]);
        $submissionId = $subRes->json('data.id');

        $pendingShare = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/share-links", [
                'label' => 'Too early',
            ]);
        $pendingShare->assertStatus(422);

        $inbox = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');
        $approvalId = $inbox->json('data.0.id');

        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
                'signature' => 'Approver',
                'signature_consent' => true,
                'signature_storage_consent' => true,
            ])
            ->assertOk();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/share-links", [
                'label' => 'Vendor copy',
                'ttl_days' => 7,
            ]);
        $create->assertCreated();
        $url = (string) $create->json('data.url');
        $this->assertStringContainsString('/public/e-approval/shared/', $url);
        $plain = basename(parse_url($url, PHP_URL_PATH) ?: '');

        $public = $this->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/public/e-approval/shared/'.$plain);
        $public->assertOk();
        $this->assertSame('Shared doc', $public->json('data.values.0.display_value')
            ?? $public->json('data.values.0.value'));
        $this->assertSame(EApprovalSubmissionStatus::APPROVED, $public->json('data.status'));

        $shareLinkId = $create->json('data.link.id');
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/share-links/{$shareLinkId}/revoke")
            ->assertOk();

        $this->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/public/e-approval/shared/'.$plain)
            ->assertStatus(422);

        tenancy()->initialize($this->testTenant);
        $this->assertNotNull(EApprovalSubmissionShareLink::query()->find($shareLinkId)?->revoked_at);
        tenancy()->end();
    }
}
