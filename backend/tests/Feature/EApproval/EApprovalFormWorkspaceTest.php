<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFormWorkspaceSupport;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFormWorkspaceTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
            EnsureActiveSession::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_workspace_dashboard_for_iso_slug(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoForm();

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-ISO-WS-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG)
            ->assertOk()
            ->assertJsonPath('data.form.id', (string) $form->id)
            ->assertJsonPath('data.workspace.slug', EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG)
            ->assertJsonPath('data.kpis.0.key', 'pending');
    }

    public function test_workspace_dashboard_includes_layout_config(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoForm();

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG)
            ->assertOk()
            ->assertJsonPath('data.dashboard.widgets.0.type', 'kpis')
            ->assertJsonPath('data.dashboard.saved_views.0.id', 'all')
            ->assertJsonStructure([
                'data' => [
                    'dashboard' => ['widgets', 'table_columns', 'saved_views'],
                    'status_breakdown',
                    'recent_activity',
                    'available_columns',
                ],
            ]);
    }

    public function test_workspace_submissions_include_configured_field_values(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoFormWithDashboardFieldColumn();
        $titleField = \App\Modules\EApproval\Models\EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->where('name', 'document_title')
            ->firstOrFail();

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-DASH-FIELD',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => $titleField->id,
            'value' => 'Dashboard SOP',
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG.'/submissions')
            ->assertOk()
            ->assertJsonPath('data.0.document_no', 'ATC-DASH-FIELD')
            ->assertJsonPath('data.0.field_values.document_title', 'Dashboard SOP');
    }

    public function test_workspace_slug_must_be_unique_among_published_forms(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->createIsoForm();

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Duplicate ISO workspace',
                'status' => 'published',
                'fields' => [['type' => 'text', 'name' => 'summary', 'label' => 'Summary']],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
                'metadata_json' => [
                    'workspace' => [
                        'enabled' => true,
                        'slug' => EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG,
                    ],
                ],
            ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['metadata_json.workspace.slug']);
    }

    public function test_submissions_index_filters_by_form_id(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoForm();
        $otherForm = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Other form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-ISO-A',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $otherForm->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-OTHER-A',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/submissions?form_id='.$form->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.document_no', 'ATC-ISO-A');
    }

    public function test_workspace_export_requires_coordinator_permission_when_show_export_enabled(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoFormWithExportEnabled();
        $requestor = TenantUser::query()->create([
            'name' => 'Requestor Only',
            'email' => 'requestor@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->assignRole('e_approval_requestor');

        tenancy()->end();

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG.'/export')
            ->assertForbidden();
    }

    public function test_workspace_export_allows_form_coordinator_without_audit_permission(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoFormWithExportEnabled();
        $coordinator = TenantUser::query()->create([
            'name' => 'Workspace Coordinator',
            'email' => 'coordinator@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $coordinator->givePermissionTo([
            'e_approval:view',
            'e_approval:submissions:view',
            'e_approval:forms:manage',
        ]);

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-ISO-EXPORT-001',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $response = $this->actingAs($coordinator, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG.'/export');

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('ATC-ISO-EXPORT-001', $response->streamedContent());
    }

    public function test_workspace_export_scopes_rows_to_viewer_visibility(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoFormWithExportEnabled();

        $coordinator = TenantUser::query()->create([
            'name' => 'Workspace Coordinator',
            'email' => 'coordinator-scope@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $coordinator->givePermissionTo([
            'e_approval:view',
            'e_approval:submissions:view',
            'e_approval:forms:manage',
        ]);

        $requestorB = TenantUser::query()->create([
            'name' => 'Requestor B',
            'email' => 'requestor-b@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestorB->assignRole('e_approval_requestor');

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $coordinator->id,
            'document_no' => 'ATC-SCOPE-A',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $requestorB->id,
            'document_no' => 'ATC-SCOPE-B',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $allRows = $this->actingAs($coordinator, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG.'/export');

        $allRows->assertOk();
        $allCsv = $allRows->streamedContent();
        $this->assertStringContainsString('ATC-SCOPE-A', $allCsv);
        $this->assertStringContainsString('ATC-SCOPE-B', $allCsv);

        $mineOnly = $this->actingAs($coordinator, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG.'/export?mine=1');

        $mineOnly->assertOk();
        $mineCsv = $mineOnly->streamedContent();
        $this->assertStringContainsString('ATC-SCOPE-A', $mineCsv);
        $this->assertStringNotContainsString('ATC-SCOPE-B', $mineCsv);
    }

    public function test_workspace_export_includes_form_field_columns(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoFormWithExportEnabled();
        $titleField = EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'document_title',
            'label' => 'Document title',
            'step_order' => 1,
        ]);

        $submission = EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-FIELD-EXPORT',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalFormValue::query()->create([
            'id' => (string) Str::uuid(),
            'submission_id' => $submission->id,
            'field_id' => $titleField->id,
            'value' => 'Controlled SOP v2',
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->get('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG.'/export');

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Document title', $csv);
        $this->assertStringContainsString('Controlled SOP v2', $csv);
        $this->assertStringContainsString('ATC-FIELD-EXPORT', $csv);
    }

    public function test_workspace_denies_access_when_acl_roles_missing(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->createWorkspaceForm('acl-gated', [
            'acl' => ['roles' => ['e_approval_approver'], 'enforce_form_restricted_to' => true],
        ]);

        $viewer = TenantUser::query()->create([
            'name' => 'Requestor Viewer',
            'email' => 'requestor-acl@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $viewer->assignRole('e_approval_requestor');
        $viewer->givePermissionTo(['e_approval:view', 'e_approval:submissions:view']);

        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/acl-gated')
            ->assertForbidden();
    }

    public function test_workspace_allows_viewer_with_acl_role(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->createWorkspaceForm('acl-open', [
            'acl' => ['roles' => ['e_approval_approver'], 'enforce_form_restricted_to' => false],
        ]);

        $approver = TenantUser::query()->create([
            'name' => 'Approver Viewer',
            'email' => 'approver-acl@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $approver->assignRole('e_approval_approver');
        $approver->givePermissionTo(['e_approval:view', 'e_approval:submissions:view']);

        tenancy()->end();

        $this->actingAs($approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/acl-open')
            ->assertOk()
            ->assertJsonPath('data.workspace.slug', 'acl-open');
    }

    public function test_workspace_enforces_form_restricted_to_roles(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->createWorkspaceForm('restricted-form', [
            'acl' => ['roles' => [], 'enforce_form_restricted_to' => true],
        ], 'e_approval_approver');

        $requestor = TenantUser::query()->create([
            'name' => 'Restricted Requestor',
            'email' => 'restricted-requestor@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->assignRole('e_approval_requestor');
        $requestor->givePermissionTo(['e_approval:view', 'e_approval:submissions:view']);

        tenancy()->end();

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/restricted-form')
            ->assertForbidden();
    }

    public function test_multi_form_workspace_submissions_include_linked_forms(): void
    {
        tenancy()->initialize($this->testTenant);

        $linked = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Linked PMO Form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
        ]);

        $primary = $this->createWorkspaceForm('pmo-hub', [
            'forms' => [
                'mode' => 'multi',
                'linked_form_ids' => [(string) $linked->id],
            ],
        ]);

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $primary->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-PMO-PRIMARY',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        EApprovalSubmission::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $linked->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'document_no' => 'ATC-PMO-LINKED',
            'status' => EApprovalSubmissionStatus::PENDING,
            'current_step' => 1,
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/pmo-hub')
            ->assertOk()
            ->assertJsonPath('data.is_multi_form', true)
            ->assertJsonCount(2, 'data.forms');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/pmo-hub/submissions');

        $response->assertOk();
        $documentNos = collect($response->json('data'))->pluck('document_no')->all();
        $this->assertContains('ATC-PMO-PRIMARY', $documentNos);
        $this->assertContains('ATC-PMO-LINKED', $documentNos);
    }

    public function test_workspace_dashboard_includes_recent_audit(): void
    {
        tenancy()->initialize($this->testTenant);

        $form = $this->createIsoForm();

        EApprovalAuditLog::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $this->testTenantAdmin->id,
            'action' => 'workspace_form_updated',
            'target_id' => (string) $form->id,
            'remarks' => 'Workspace audit test',
        ]);

        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces/'.EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG)
            ->assertOk()
            ->assertJsonPath('data.recent_audit.0.action', 'workspace_form_updated')
            ->assertJsonPath('data.recent_audit.0.user_name', $this->testTenantAdmin->name);
    }

    public function test_workspace_sidebar_list_hides_inaccessible_workspaces(): void
    {
        tenancy()->initialize($this->testTenant);

        $this->createWorkspaceForm('hidden-workspace', [
            'acl' => ['roles' => ['e_approval_approver'], 'enforce_form_restricted_to' => false],
        ]);

        $requestor = TenantUser::query()->create([
            'name' => 'Sidebar Requestor',
            'email' => 'sidebar-requestor@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->assignRole('e_approval_requestor');
        $requestor->givePermissionTo(['e_approval:view', 'e_approval:submissions:view']);

        tenancy()->end();

        $response = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces');

        $response->assertOk();
        $slugs = collect($response->json('data.items'))->pluck('slug')->all();
        $this->assertNotContains('hidden-workspace', $slugs);
    }

    public function test_iso_form_without_enabled_workspace_not_in_sidebar(): void
    {
        tenancy()->initialize($this->testTenant);

        EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval DRAFT',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'metadata_json' => [
                'form_family' => EApprovalFormWorkspaceSupport::ISO_FORM_FAMILY,
            ],
        ]);

        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/workspaces');

        $response->assertOk();
        $slugs = collect($response->json('data.items'))->pluck('slug')->all();
        $this->assertNotContains(EApprovalFormWorkspaceSupport::ISO_WORKSPACE_SLUG, $slugs);
    }

    private function createWorkspaceForm(
        string $slug,
        array $workspaceOverrides = [],
        ?string $restrictedTo = null,
    ): EApprovalForm {
        $workspace = array_replace_recursive([
            'enabled' => true,
            'slug' => $slug,
            'title' => 'Test Workspace',
            'description' => 'ACL test workspace',
            'visibility' => 'workspace_all',
            'nav' => ['show_in_sidebar' => true, 'section' => 'Operate'],
            'actions' => ['new_request_mode' => 'focused', 'show_export' => false],
        ], $workspaceOverrides);

        return EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'Workspace '.$slug,
            'description' => 'Test workspace form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'restricted_to' => $restrictedTo,
            'metadata_json' => [
                'workspace' => $workspace,
            ],
        ]);
    }

    private function createIsoForm(): EApprovalForm
    {
        return EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'description' => 'Pilot workspace form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'metadata_json' => [
                'form_family' => EApprovalFormWorkspaceSupport::ISO_FORM_FAMILY,
                'workspace' => EApprovalFormWorkspaceSupport::isoPilotDefaults('ISO Approval'),
            ],
        ]);
    }

    private function createIsoFormWithDashboardFieldColumn(): EApprovalForm
    {
        $workspace = EApprovalFormWorkspaceSupport::isoPilotDefaults('ISO Approval');
        $workspace['dashboard'] = [
            'widgets' => [
                ['id' => 'submissions_table', 'type' => 'submissions_table', 'enabled' => true, 'order' => 1],
            ],
            'table_columns' => [
                ['key' => 'document_no', 'label' => 'Document', 'kind' => 'system', 'visible' => true, 'order' => 1],
                ['key' => 'field:document_title', 'label' => 'Document title', 'kind' => 'field', 'field_name' => 'document_title', 'visible' => true, 'order' => 2],
            ],
            'saved_views' => [
                ['id' => 'all', 'label' => 'All', 'order' => 1],
            ],
        ];

        $form = EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'description' => 'Pilot workspace form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'metadata_json' => [
                'form_family' => EApprovalFormWorkspaceSupport::ISO_FORM_FAMILY,
                'workspace' => $workspace,
            ],
        ]);

        EApprovalFormField::query()->create([
            'id' => (string) Str::uuid(),
            'form_id' => $form->id,
            'type' => 'text',
            'name' => 'document_title',
            'label' => 'Document title',
            'step_order' => 1,
        ]);

        return $form;
    }

    private function createIsoFormWithExportEnabled(): EApprovalForm
    {
        $workspace = EApprovalFormWorkspaceSupport::isoPilotDefaults('ISO Approval');
        $workspace['actions']['show_export'] = true;

        return EApprovalForm::query()->create([
            'id' => (string) Str::uuid(),
            'name' => 'ISO Approval',
            'description' => 'Pilot workspace form',
            'category' => 'general',
            'status' => 'published',
            'schema_version' => 1,
            'owner_code' => 'GEN',
            'doc_type_code' => 'F',
            'metadata_json' => [
                'form_family' => EApprovalFormWorkspaceSupport::ISO_FORM_FAMILY,
                'workspace' => $workspace,
            ],
        ]);
    }
}
