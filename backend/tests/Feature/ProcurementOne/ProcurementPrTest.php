<?php

declare(strict_types=1);

namespace Tests\Feature\ProcurementOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class ProcurementPrTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

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
                'project_one',
                'e_approval',
                'procurement_one',
            ],
        ]);

        $this->bootInMemoryTenantApi();

        $this->testTenant->plan_tier = 'professional';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_create_submit_and_approve_pr_projects_to_procurement_one(): void
    {
        $formId = $this->createPurchaseRequisitionForm();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Tower battery bank replacement',
                'department' => 'operations',
                'urgency' => 'urgent',
                'justification' => 'Critical site power resilience upgrade.',
                'lines' => [
                    ['description' => 'Battery bank', 'quantity' => 2, 'unit_price' => 150000],
                    ['description' => 'Installation labor', 'quantity' => 1, 'unit_price' => 25000],
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.status', ProcurementPrStatus::DRAFT)
            ->assertJsonPath('data.title', 'Tower battery bank replacement');

        $prId = (string) $create->json('data.id');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.pr.status', ProcurementPrStatus::PENDING_APPROVAL)
            ->assertJsonPath('data.pr.document_no', fn ($value) => is_string($value) && $value !== '');

        $this->approveSubmission((string) $submit->json('data.pr.e_approval_submission_id'));

        tenancy()->initialize($this->testTenant);
        $pr = ProcurementPr::query()->find($prId);
        $this->assertNotNull($pr);
        $this->assertSame(ProcurementPrStatus::APPROVED, $pr->status);
        $this->assertNotNull($pr->approved_at);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/prs/{$prId}")
            ->assertOk()
            ->assertJsonPath('data.status', ProcurementPrStatus::APPROVED);
    }

    public function test_pr_index_lists_created_requisitions(): void
    {
        $this->createDraftPr();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/prs');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.status', ProcurementPrStatus::DRAFT);
    }

    public function test_pr_index_scopes_to_requestor_without_manage_permission(): void
    {
        $prId = $this->createDraftPr();

        tenancy()->initialize($this->testTenant);
        $otherViewer = TenantUser::query()->create([
            'name' => 'Other Viewer',
            'email' => 'other.viewer@towerone.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $otherViewer->assignRole('viewer');
        tenancy()->end();

        $response = $this->actingAs($otherViewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/prs');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0);

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains($prId, $ids);
    }

    public function test_pr_show_denies_other_requestor_without_manage_permission(): void
    {
        $prId = $this->createDraftPr();

        tenancy()->initialize($this->testTenant);
        $otherViewer = TenantUser::query()->create([
            'name' => 'Other Viewer',
            'email' => 'other.viewer2@towerone.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $otherViewer->assignRole('viewer');
        tenancy()->end();

        $this->actingAs($otherViewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/prs/{$prId}")
            ->assertForbidden();
    }

    public function test_form_schema_and_values_payload_create_pr(): void
    {
        $this->createPurchaseRequisitionForm();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/procurement-one/prs/form-schema')
            ->assertOk()
            ->assertJsonPath('data.form.metadata.form_family', 'purchase_requisition')
            ->assertJsonStructure(['data' => ['form' => ['id', 'name', 'status'], 'fields']]);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'Schema-driven PR',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'Created from published form values.',
                    'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
                    'estimated_total' => '4800',
                ],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.title', 'Schema-driven PR')
            ->assertJsonPath('data.status', ProcurementPrStatus::DRAFT)
            ->assertJsonStructure(['data' => ['compose_values' => ['requisition_title']]]);
    }

    public function test_values_payload_rejects_invalid_optional_link_ids(): void
    {
        $this->createPurchaseRequisitionForm();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'Invalid link fields',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'Should fail validation.',
                    'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
                    'project_id' => 'asdasd',
                    'rollout_id' => 'asd',
                    'site_id' => 'asdasd',
                    'boq_line_id' => 'asd',
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['values.site_id', 'values.project_id', 'values.rollout_id', 'values.boq_line_id']);
    }

    public function test_compose_can_create_multiple_draft_prs_without_submission_collision(): void
    {
        $this->createPurchaseRequisitionForm();

        $payload = [
            'values' => [
                'requisition_title' => 'Schema-driven PR',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Created from published form values.',
                'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
            ],
        ];

        $first = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', $payload);

        $first->assertCreated();

        $second = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    ...$payload['values'],
                    'requisition_title' => 'Second schema-driven PR',
                ],
            ]);

        $second->assertCreated()
            ->assertJsonPath('data.title', 'Second schema-driven PR');

        $firstSubmissionId = (string) $first->json('data.e_approval_submission_id');
        $secondSubmissionId = (string) $second->json('data.e_approval_submission_id');

        $this->assertNotSame($first->json('data.id'), $second->json('data.id'));
        $this->assertNotEmpty($firstSubmissionId);
        $this->assertNotEmpty($secondSubmissionId);
        $this->assertNotSame($firstSubmissionId, $secondSubmissionId);
    }

    public function test_compose_values_sync_approvers_to_e_approval_submission(): void
    {
        $this->createPurchaseRequisitionFormWithApprovers();
        $approverId = (string) $this->testTenantAdmin->id;

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'PR with approvers',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'Compose approver sync test.',
                    'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
                    'estimated_total' => '4800',
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $create->assertCreated();

        $prId = (string) $create->json('data.id');
        $submissionId = (string) $create->json('data.e_approval_submission_id');
        $this->assertNotEmpty($submissionId);

        tenancy()->initialize($this->testTenant);
        $pr = ProcurementPr::query()->findOrFail($prId);
        $submission = EApprovalSubmission::query()->with('values.field')->findOrFail($submissionId);
        $procurementApprover = $submission->values->first(
            static fn ($value) => ($value->field?->name ?? '') === 'procurement_approver',
        );
        $financeApprover = $submission->values->first(
            static fn ($value) => ($value->field?->name ?? '') === 'finance_approver',
        );
        $storedComposeApprover = $pr->metadata_json['compose_form_values']['procurement_approver'] ?? null;
        tenancy()->end();

        $this->assertSame($approverId, $procurementApprover?->value);
        $this->assertSame($approverId, $financeApprover?->value);
        $this->assertSame($approverId, $storedComposeApprover);

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.pr.status', ProcurementPrStatus::PENDING_APPROVAL);

        tenancy()->initialize($this->testTenant);
        $submitted = EApprovalSubmission::query()->with(['values.field', 'approvals'])->findOrFail($submissionId);
        $submittedProcurementApprover = $submitted->values->first(
            static fn ($value) => ($value->field?->name ?? '') === 'procurement_approver',
        );
        tenancy()->end();

        $this->assertSame($approverId, $submittedProcurementApprover?->value);
        $this->assertSame('pending', $submitted->status);
        $this->assertGreaterThan(0, $submitted->approvals->count());
    }

    public function test_compose_end_to_end_save_upload_submit_and_detail(): void
    {
        Storage::fake('tenant_files');
        $this->createPurchaseRequisitionFormWithApproversAndQuotes();
        $approverId = (string) $this->testTenantAdmin->id;

        $values = [
            'requisition_title' => 'E2E compose PR draft',
            'department' => 'operations',
            'urgency' => 'normal',
            'justification' => 'End-to-end compose verification.',
            'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
            'estimated_total' => '4800',
            'procurement_approver' => $approverId,
            'finance_approver' => $approverId,
        ];

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', ['values' => $values]);

        $create->assertCreated()
            ->assertJsonPath('data.status', ProcurementPrStatus::DRAFT);

        $prId = (string) $create->json('data.id');
        $submissionId = (string) $create->json('data.e_approval_submission_id');

        $update = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson("/api/v1/procurement-one/prs/{$prId}", [
                'values' => [
                    ...$values,
                    'requisition_title' => 'E2E compose PR updated',
                ],
            ]);

        $update->assertOk()
            ->assertJsonPath('data.title', 'E2E compose PR updated')
            ->assertJsonPath('data.id', $prId);

        $upload = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/procurement-one/prs/{$prId}/attachments", [
                'file' => UploadedFile::fake()->create('vendor-quote.pdf', 100, 'application/pdf'),
                'field_name' => 'quotes',
            ]);

        $upload->assertCreated()
            ->assertJsonPath('data.field_name', 'quotes')
            ->assertJsonPath('data.file_name', 'vendor-quote.pdf');

        $attachmentId = (string) $upload->json('data.id');
        $eApprovalAttachmentId = (string) $upload->json('data.e_approval_attachment_id');
        $this->assertNotEmpty($eApprovalAttachmentId);

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.pr.id', $prId)
            ->assertJsonPath('data.pr.status', ProcurementPrStatus::PENDING_APPROVAL)
            ->assertJsonPath('data.pr.document_no', fn ($value) => is_string($value) && $value !== '');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->post("/api/v1/procurement-one/prs/{$prId}/attachments", [
                'file' => UploadedFile::fake()->create('late-quote.pdf', 100, 'application/pdf'),
                'field_name' => 'quotes',
            ])
            ->assertStatus(422);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/prs/{$prId}");

        $detail->assertOk()
            ->assertJsonPath('data.status', ProcurementPrStatus::PENDING_APPROVAL)
            ->assertJsonPath('data.title', 'E2E compose PR updated')
            ->assertJsonPath('data.compose_values.procurement_approver', $approverId)
            ->assertJsonPath('data.compose_values.finance_approver', $approverId)
            ->assertJsonPath('data.compose_values.requisition_title', 'E2E compose PR updated')
            ->assertJsonCount(1, 'data.attachments')
            ->assertJsonPath('data.attachments.0.id', $attachmentId)
            ->assertJsonPath('data.attachments.0.field_name', 'quotes');

        $submission = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}");

        $submission->assertOk()
            ->assertJsonPath('data.id', $submissionId)
            ->assertJsonPath('data.status', 'pending');

        $submissionValues = collect($submission->json('data.values') ?? [])
            ->keyBy(static fn (array $row) => (string) ($row['field_name'] ?? ''));
        $this->assertSame($approverId, $submissionValues->get('procurement_approver')['value'] ?? null);
        $this->assertSame($approverId, $submissionValues->get('finance_approver')['value'] ?? null);
        $this->assertSame('E2E compose PR updated', $submissionValues->get('requisition_title')['value'] ?? null);

        $submissionAttachments = collect($submission->json('data.attachments') ?? []);
        $this->assertTrue(
            $submissionAttachments->contains(
                static fn (array $row) => (string) ($row['id'] ?? '') === $eApprovalAttachmentId,
            ),
        );
    }

    public function test_compose_values_preserve_grid_line_items_on_detail(): void
    {
        $this->createPurchaseRequisitionForm();

        $lineItems = '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}';

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'Grid round-trip PR',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'Grid compose round-trip test.',
                    'line_items' => $lineItems,
                    'estimated_total' => '4800',
                ],
            ]);

        $create->assertCreated();

        $prId = (string) $create->json('data.id');

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/procurement-one/prs/{$prId}");

        $detail->assertOk()
            ->assertJsonPath('data.lines.0.description', 'Cable tray')
            ->assertJsonPath('data.compose_values.requisition_title', 'Grid round-trip PR');

        $composeLineItems = (string) $detail->json('data.compose_values.line_items');
        $this->assertNotSame('', $composeLineItems);

        $decoded = json_decode($composeLineItems, true);
        $this->assertIsArray($decoded);

        $rows = array_is_list($decoded) ? $decoded : ($decoded['rows'] ?? []);
        $this->assertNotEmpty($rows);

        $firstRow = $rows[0];
        $this->assertTrue(
            ($firstRow['Description'] ?? $firstRow['0'] ?? null) === 'Cable tray'
            || str_contains(json_encode($firstRow, JSON_THROW_ON_ERROR), 'Cable tray'),
        );
    }

    public function test_submit_rejects_missing_required_compose_fields(): void
    {
        $this->createPurchaseRequisitionFormWithApprovers();

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'Incomplete PR',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'Missing approvers on submit.',
                    'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
                    'estimated_total' => '4800',
                ],
            ]);

        $create->assertCreated();
        $prId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.procurement_approver', 'values.finance_approver']);
    }

    public function test_compose_submit_uses_form_workflow_when_policy_enabled_but_fixed_steps_configured(): void
    {
        $approverId = (string) $this->testTenantAdmin->id;
        $this->createPolicyEnabledPrFormWithFixedWorkflow($approverId);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'High value PR',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'CapEx equipment purchase.',
                    'line_items' => '{"rows":[{"0":"Tower materials","1":"15","2":"515151"}]}',
                    'estimated_total' => '7735590',
                ],
            ]);

        $create->assertCreated();
        $prId = (string) $create->json('data.id');
        $submissionId = (string) $create->json('data.e_approval_submission_id');

        $submit = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit");

        $submit->assertOk()
            ->assertJsonPath('data.pr.status', 'pending_approval');

        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->with('approvals')->findOrFail($submissionId);
        tenancy()->end();

        $this->assertSame('pending', $submission->status);
        $this->assertGreaterThan(0, $submission->approvals->count());
        $this->assertNull($submission->approval_policy_version_id);
    }

    public function test_submit_rejects_missing_required_file_attachment(): void
    {
        Storage::fake('tenant_files');
        $this->createPurchaseRequisitionFormWithApproversAndQuotes();
        $approverId = (string) $this->testTenantAdmin->id;

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'values' => [
                    'requisition_title' => 'PR missing quote file',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'justification' => 'Required quote not uploaded.',
                    'line_items' => '{"rows":[{"0":"Cable tray","1":"4","2":"1200"}]}',
                    'estimated_total' => '4800',
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $create->assertCreated();
        $prId = (string) $create->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/procurement-one/prs/{$prId}/submit")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['values.quotes']);
    }

    private function createDraftPr(): string
    {
        $this->createPurchaseRequisitionForm();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/procurement-one/prs', [
                'title' => 'Office supplies',
                'department' => 'operations',
                'urgency' => 'normal',
                'justification' => 'Monthly replenishment.',
                'lines' => [
                    ['description' => 'Paper', 'quantity' => 10, 'unit_price' => 250],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.id');
    }

    private function createPurchaseRequisitionForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition',
                'description' => 'PR test form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'print_template_kind' => 'purchase_requisition',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal'], ['value' => 'urgent', 'label' => 'Urgent']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseRequisitionFormWithApprovers(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition with approvers',
                'description' => 'PR compose approver sync test form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'print_template_kind' => 'purchase_requisition',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal'], ['value' => 'urgent', 'label' => 'Urgent']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'procurement_approver', 'label' => 'Procurement approver', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'finance_approver', 'label' => 'Finance approver', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPurchaseRequisitionFormWithApproversAndQuotes(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition E2E compose',
                'description' => 'PR compose end-to-end test form',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'print_template_kind' => 'purchase_requisition',
                    'use_approval_policy' => false,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal'], ['value' => 'urgent', 'label' => 'Urgent']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'procurement_approver', 'label' => 'Procurement approver', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'finance_approver', 'label' => 'Finance approver', 'validation' => ['required' => true]],
                    ['type' => 'file', 'name' => 'quotes', 'label' => 'Vendor quotes', 'validation' => ['required' => true, 'maxFiles' => 3]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->testTenantAdmin->id, 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPolicyEnabledPrFormWithFixedWorkflow(string $approverId): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Purchase requisition policy with fixed workflow',
                'description' => 'PR form workflow overrides DOA policy',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'print_template_kind' => 'purchase_requisition',
                    'use_approval_policy' => true,
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'requisition_title', 'label' => 'Title', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal'], ['value' => 'urgent', 'label' => 'Urgent']]]],
                    ['type' => 'grid', 'name' => 'line_items', 'label' => 'Lines', 'validation' => ['required' => true], 'options' => ['columns' => [['label' => 'Description', 'type' => 'text'], ['label' => 'Qty', 'type' => 'number'], ['label' => 'Unit price', 'type' => 'currency']]]],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Total', 'validation' => ['required' => true]],
                    ['type' => 'textarea', 'name' => 'justification', 'label' => 'Justification', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 1],
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 2],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function approveSubmission(string $submissionId): void
    {
        $inbox = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $inbox->assertOk();

        $approvalId = collect($inbox->json('data'))
            ->firstWhere('submission_id', $submissionId)['id'] ?? $inbox->json('data.0.id');

        $this->assertNotEmpty($approvalId);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();
    }
}
