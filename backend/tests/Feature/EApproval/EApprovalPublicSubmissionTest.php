<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
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

    public function test_public_form_show_hydrates_master_data_choices(): void
    {
        tenancy()->initialize($this->testTenant);
        Site::query()->create([
            'site_code' => 'SITE-100',
            'name' => 'Alpha Tower',
            'status' => 'active',
        ]);
        tenancy()->end();

        $formId = $this->createPublishedFormWithSiteLookup();
        $token = $this->createPublicLink($formId);

        $show = $this->withHeaders($this->publicApiHeaders())
            ->getJson('/api/v1/public/e-approval/forms/'.$token);

        $show->assertOk();

        $siteField = collect($show->json('data.form.fields'))->firstWhere('name', 'site_id');
        $this->assertIsArray($siteField);
        $options = $siteField['options'] ?? [];
        $this->assertIsArray($options);
        $this->assertArrayNotHasKey('master_data_key', $options);
        $this->assertArrayNotHasKey('masterDataKey', $options);

        $choices = $options['choices'] ?? [];
        $this->assertIsArray($choices);
        $this->assertNotEmpty($choices);
        $this->assertSame('SITE-100', $choices[0]['value']);
        $this->assertStringContainsString('SITE-100', (string) $choices[0]['label']);
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

    public function test_public_share_url_can_be_revealed_after_create(): void
    {
        $formId = $this->createPublishedForm();
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/public-links", [
                'label' => 'Vendor portal',
                'sponsor_user_id' => (string) $this->testTenantAdmin->id,
            ])
            ->assertCreated();

        $publicUrl = (string) $create->json('data.public_url');
        $linkId = (string) $create->json('data.link.id');
        $this->assertNotSame('', $publicUrl);
        $this->assertTrue((bool) $create->json('data.link.can_reveal_url'));

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/public-links/{$linkId}/reveal")
            ->assertOk()
            ->assertJsonPath('data.public_url', $publicUrl);

        $share = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}/public-share-url")
            ->assertOk();

        $this->assertSame($publicUrl, (string) $share->json('data.public_url'));
        $this->assertSame($linkId, (string) $share->json('data.link_id'));

        $forms = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/forms?status=published&per_page=100')
            ->assertOk();

        $row = collect($forms->json('data'))->firstWhere('id', $formId);
        $this->assertIsArray($row);
        $this->assertTrue((bool) ($row['has_shareable_public_link'] ?? false));
    }

    public function test_public_submit_requires_pending_attachment_counts_for_required_files(): void
    {
        $this->testTenant->plan_tier = 'professional';
        $this->testTenant->save();

        $formId = $this->createPublishedFormWithRequiredFile();
        $token = $this->createPublicLink($formId);

        $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Need access'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['values.core_access_documents']);

        $this->withHeaders($this->publicApiHeaders())
            ->postJson('/api/v1/public/e-approval/forms/'.$token.'/submissions', [
                'submitter_name' => 'Acme Vendor',
                'submitter_email' => 'vendor@example.com',
                'values' => ['reason' => 'Need access'],
                'pending_attachment_counts' => [
                    'core_access_documents' => 2,
                ],
            ])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['submission_id', 'document_no', 'upload_token']]);
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

    private function createPublishedFormWithRequiredFile(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Vendor intake with files',
                'description' => 'External vendors',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                    [
                        'type' => 'file',
                        'name' => 'core_access_documents',
                        'label' => '14. Upload file (SOW, MOP, SP and ID\'s & Certifications)',
                        'validation' => ['required' => true, 'maxFiles' => 15],
                    ],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPublishedFormWithSiteLookup(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Site access',
                'description' => 'External site access',
                'status' => 'published',
                'fields' => [
                    [
                        'type' => 'select',
                        'name' => 'site_id',
                        'label' => 'Site ID',
                        'options' => ['master_data_key' => 'sites'],
                    ],
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
