<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class WorkspaceSearchTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'sites',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $this->site = Site::query()->create([
            'site_code' => 'WS-SEARCH-001',
            'name' => 'Workspace Search Test Site',
            'status' => 'active',
        ]);
        tenancy()->end();
    }

    public function test_workspace_search_returns_matching_entities(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=WS-SEARCH');

        $response->assertOk()
            ->assertJsonFragment([
                'module' => 'sites',
                'entity_type' => 'site',
                'id' => (string) $this->site->id,
                'title' => 'WS-SEARCH-001 · Workspace Search Test Site',
                'href' => '/sites/'.$this->site->id,
            ]);
    }

    public function test_workspace_search_includes_controlled_documents(): void
    {
        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'document_register',
            ],
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        \App\Modules\Documents\Models\ControlledDocument::query()->create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'document_code' => 'WS-CD-SEARCH-001',
            'title' => 'Quality Manual',
            'department' => 'QMS',
            'current_revision' => 1,
            'status' => \App\Modules\Documents\Support\ControlledDocumentStatus::PUBLISHED,
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=WS-CD-SEARCH')
            ->assertOk()
            ->assertJsonFragment([
                'module' => 'document_register',
                'entity_type' => 'controlled_document',
                'title' => 'WS-CD-SEARCH-001',
            ]);
    }

    public function test_workspace_search_returns_empty_for_short_query(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=a')
            ->assertOk()
            ->assertJsonPath('data', []);
    }

    public function test_workspace_search_requires_permission(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions([]);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=WS-SEARCH')
            ->assertForbidden();
    }

    public function test_e_approval_search_matches_status_keywords_and_requestor(): void
    {
        Notification::fake();

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'e_approval',
            ],
            'toweros.notifications_mail_mailer' => 'array',
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $requestor = TenantUser::query()->create([
            'name' => 'Revision Requestor',
            'email' => 'revision.requestor@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->assignRole('e_approval_requestor');
        $approver = TenantUser::query()->create([
            'name' => 'Search Approver',
            'email' => 'search-approver@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver->assignRole('e_approval_approver');
        tenancy()->end();

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Workspace Search Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $approver->id, 'step_order' => 1],
                ],
            ]);
        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Searchable request'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');
        $documentNo = (string) $subRes->json('data.document_no');

        $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1')
            ->assertOk();

        $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/revision", [
                'remarks' => 'Please revise for search coverage.',
            ])
            ->assertOk();

        $byStatus = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=revision');
        $byStatus->assertOk();
        $byStatus->assertJsonFragment([
            'module' => 'e_approval',
            'entity_type' => 'submission',
            'id' => $submissionId,
            'title' => $documentNo,
            'status' => EApprovalSubmissionStatus::RETURNED,
            'status_label' => 'Needs revision',
        ]);
        $this->assertStringContainsString('Revision Requestor', (string) collect($byStatus->json('data'))
            ->firstWhere('id', $submissionId)['subtitle']);

        $byRequestor = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=revision.requestor');
        $byRequestor->assertOk()
            ->assertJsonFragment([
                'id' => $submissionId,
                'status_label' => 'Needs revision',
            ]);
    }

    public function test_e_approval_search_includes_step_and_waiting_on_for_pending(): void
    {
        Notification::fake();

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'e_approval',
            ],
            'toweros.notifications_mail_mailer' => 'array',
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        $approver = TenantUser::query()->create([
            'name' => 'Waiting On Approver',
            'email' => 'waiting-on-approver@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver->assignRole('e_approval_approver');
        tenancy()->end();

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Waiting On Search Form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $approver->id, 'step_order' => 1],
                ],
            ]);
        $formRes->assertCreated();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formRes->json('data.form.id'),
                'values' => ['reason' => 'Pending for search'],
            ]);
        $subRes->assertCreated();
        $submissionId = (string) $subRes->json('data.id');
        $documentNo = (string) $subRes->json('data.document_no');

        $search = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q='.rawurlencode($documentNo));

        $search->assertOk()
            ->assertJsonFragment([
                'id' => $submissionId,
                'title' => $documentNo,
                'status' => EApprovalSubmissionStatus::PENDING,
                'current_step' => 1,
                'waiting_on' => 'Waiting On Approver',
            ]);

        $hit = collect($search->json('data'))->firstWhere('id', $submissionId);
        $this->assertIsArray($hit);
        $this->assertStringContainsString('Step 1', (string) $hit['subtitle']);
        $this->assertStringContainsString('Waiting on Waiting On Approver', (string) $hit['subtitle']);
    }

    public function test_e_approval_search_includes_published_forms_not_drafts(): void
    {
        Notification::fake();

        config([
            'toweros.tenant_modules.enabled' => [
                'core',
                'team_access',
                'e_approval',
            ],
            'toweros.notifications_mail_mailer' => 'array',
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();

        $published = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Published Alpha Form Search',
                'description' => 'Published form for workspace search',
                'category' => 'Operations',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);
        $published->assertCreated();
        $publishedId = (string) $published->json('data.form.id');

        $draft = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Draft Alpha Form Search',
                'status' => 'draft',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);
        $draft->assertCreated();
        $draftId = (string) $draft->json('data.form.id');

        $search = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/search?q=Alpha%20Form%20Search');

        $search->assertOk()
            ->assertJsonFragment([
                'module' => 'e_approval',
                'entity_type' => 'form',
                'id' => $publishedId,
                'title' => 'Published Alpha Form Search',
                'status' => 'published',
                'status_label' => 'Published',
                'href' => '/e-approval/forms/'.$publishedId,
            ]);

        $ids = collect($search->json('data'))->pluck('id')->all();
        $this->assertContains($publishedId, $ids);
        $this->assertNotContains($draftId, $ids);
    }
}
