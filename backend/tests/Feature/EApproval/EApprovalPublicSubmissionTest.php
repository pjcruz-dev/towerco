<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalPublicSubmissionTest extends TestCase
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

    public function test_public_link_accepts_external_submission(): void
    {
        $formId = $this->createPublishedForm();
        $token = $this->createPublicLink($formId);

        $show = $this->withHeaders($this->publicApiHeaders())
            ->getJson('/api/v1/public/e-approval/forms/'.$token);

        $show->assertOk()
            ->assertJsonPath('data.form.name', 'Vendor intake')
            ->assertJsonPath('data.requires_password', false);

        $submit = $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Site access request'],
            ]);

        $submit->assertCreated()
            ->assertJsonStructure(['data' => ['submission_id', 'document_no', 'upload_token']]);

        $submissionId = $submit->json('data.submission_id');

        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->find($submissionId);
        $this->assertNotNull($submission);
        $this->assertSame('external', $submission->submission_source);
        $this->assertSame('Acme Vendor', $submission->external_submitter_name);
        $this->assertSame('vendor@example.com', $submission->external_submitter_email);
        $this->assertSame((string) $this->testTenantAdmin->id, (string) $submission->requestor_id);
        $this->assertSame('pending', $submission->status);
        tenancy()->end();

        $inbox = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();
        $this->assertNotEmpty($inbox->json('data'));
    }

    public function test_revoked_public_link_rejects_submission(): void
    {
        $formId = $this->createPublishedForm();
        $token = $this->createPublicLink($formId);

        $linkId = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}/public-links")
            ->json('data.0.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/public-links/{$linkId}/revoke")
            ->assertOk();

        $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Late Vendor',
                'submitter_email' => 'late@example.com',
                'values' => ['reason' => 'Too late'],
            ])
            ->assertStatus(422);
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

    private function createPublicLink(string $formId): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/public-links", [
                'label' => 'Vendor portal',
                'sponsor_user_id' => (string) $this->testTenantAdmin->id,
            ]);

        $response->assertCreated();
        $token = (string) $response->json('data.token');
        $this->assertNotSame('', $token);

        return $token;
    }
}
