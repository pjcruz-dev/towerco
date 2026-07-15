<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Data\EApprovalApprovalPolicyDefaults;
use App\Modules\EApproval\Models\EApprovalSubmission;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalApprovalPolicyCompilerTest extends TestCase
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

    public function test_approval_policy_admin_snapshot_seeds_defaults(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approval-policies');

        $response->assertOk()
            ->assertJsonPath('data.published_version.version_number', 1)
            ->assertJsonPath(
                'data.published_version.config.workflow_profiles.pr_capex.label',
                'PR CapEx / High value',
            );
    }

    public function test_pr_over_threshold_gets_finance_step_from_policy(): void
    {
        $formId = $this->createPolicyEnabledPrForm();
        $approverId = (string) $this->testTenantAdmin->id;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'estimated_total' => (string) (EApprovalApprovalPolicyDefaults::THRESHOLD_PR_HIGH_VALUE + 1),
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.approval_policy_label', 'Approval policy v1')
            ->assertJsonPath('data.workflow_policy.workflow_profile_key', 'pr_capex');

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $this->assertCount(3, $workflow['steps']);
        $this->assertSame('role', $workflow['steps'][1]['approver_type']);
        $this->assertSame('tenant_admin', $workflow['steps'][1]['approver_id']);
        $this->assertSame('role', $workflow['steps'][2]['approver_type']);
        $this->assertSame('finance', $workflow['steps'][2]['approver_id']);
    }

    public function test_pr_under_threshold_uses_standard_profile_without_extra_finance_step(): void
    {
        $formId = $this->createPolicyEnabledPrForm();
        $approverId = (string) $this->testTenantAdmin->id;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'estimated_total' => '250000',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.workflow_policy.workflow_profile_key', 'pr_standard');

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $this->assertCount(2, $workflow['steps']);
    }

    public function test_standard_pr_submit_allows_missing_finance_approver_when_policy_does_not_require_it(): void
    {
        $formId = $this->createPolicyEnabledPrForm();
        $approverId = (string) $this->testTenantAdmin->id;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'estimated_total' => '84537',
                    'department' => 'operations',
                    'urgency' => 'normal',
                    'procurement_approver' => $approverId,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.workflow_policy.workflow_profile_key', 'pr_standard');
    }

    public function test_po_under_threshold_skips_gm_role_step(): void
    {
        $formId = $this->createPolicyEnabledPoForm();
        $approverId = (string) $this->testTenantAdmin->id;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'total_amount' => '50000',
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.workflow_policy.workflow_profile_key', 'po_standard');

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $types = array_column($workflow['steps'], 'approver_type');
        $this->assertContains('role', $types);
        $this->assertNotContains('finance', array_column($workflow['steps'], 'approver_id'));
        $this->assertCount(2, $workflow['steps']);
    }

    public function test_po_over_threshold_includes_gm_role_step(): void
    {
        $formId = $this->createPolicyEnabledPoForm();
        $approverId = (string) $this->testTenantAdmin->id;

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'total_amount' => (string) (EApprovalApprovalPolicyDefaults::THRESHOLD_PO_HIGH_VALUE + 1),
                    'procurement_approver' => $approverId,
                    'finance_approver' => $approverId,
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.workflow_policy.workflow_profile_key', 'po_high_value');

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $types = array_column($workflow['steps'], 'approver_type');
        $this->assertContains('role', $types);
        $this->assertContains('finance', array_column($workflow['steps'], 'approver_id'));
        $this->assertCount(3, $workflow['steps']);
    }

    public function test_policy_enabled_form_with_fixed_workflow_steps_uses_form_steps_not_policy(): void
    {
        $approverId = (string) $this->testTenantAdmin->id;

        $formResponse = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Policy PR with fixed workflow',
                'description' => 'Form workflow overrides DOA policy',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'use_approval_policy' => true,
                ],
                'fields' => [
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal']]]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 1],
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 2],
                ],
            ]);

        $formResponse->assertCreated();
        $formId = (string) $formResponse->json('data.form.id');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'estimated_total' => (string) (EApprovalApprovalPolicyDefaults::THRESHOLD_PR_HIGH_VALUE + 1),
                    'department' => 'operations',
                    'urgency' => 'normal',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.approval_policy_label', 'Form workflow');

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->with('approvals')->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $this->assertCount(2, $workflow['steps']);
        $this->assertSame('user', $workflow['steps'][0]['approver_type']);
        $this->assertSame('user', $workflow['steps'][1]['approver_type']);
        $this->assertGreaterThan(0, $submission->approvals->count());
    }

    public function test_non_procurement_forms_do_not_set_workflow_source_metadata(): void
    {
        $approverId = (string) $this->testTenantAdmin->id;

        $formResponse = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Leave with fixed approver',
                'description' => 'Generic forms keep classic workflow behavior',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'leave_request',
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 1],
                ],
            ]);

        $formResponse->assertCreated();
        $this->assertNull($formResponse->json('data.form.metadata_json.workflow_source'));

        $formId = (string) $formResponse->json('data.form.id');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'reason' => 'Annual leave',
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.approval_policy_label', 'Form workflow');
    }

    public function test_saving_procurement_form_sets_workflow_source_metadata(): void
    {
        $approverId = (string) $this->testTenantAdmin->id;

        $formResponse = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'PR workflow source metadata',
                'description' => 'Metadata sync test',
                'status' => 'draft',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'use_approval_policy' => true,
                ],
                'fields' => [
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                ],
            ]);

        $formResponse->assertCreated();
        $formId = (string) $formResponse->json('data.form.id');

        $this->assertSame(
            'policy',
            $formResponse->json('data.form.metadata_json.workflow_source'),
        );

        $update = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->putJson("/api/v1/e-approval/forms/{$formId}", [
                'name' => 'PR workflow source metadata',
                'status' => 'draft',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'use_approval_policy' => true,
                ],
                'fields' => [
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 1],
                    ['type' => 'user', 'approverId' => $approverId, 'step_order' => 2],
                ],
            ]);

        $update->assertOk()
            ->assertJsonPath('data.form.metadata_json.workflow_source', 'form');
    }

    private function createPolicyEnabledPrForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Policy PR',
                'description' => 'Approval policy compiler test',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                    'use_approval_policy' => true,
                ],
                'fields' => [
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total', 'validation' => ['required' => true]],
                    ['type' => 'select', 'name' => 'department', 'label' => 'Department', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'operations', 'label' => 'Operations']]]],
                    ['type' => 'select', 'name' => 'urgency', 'label' => 'Urgency', 'validation' => ['required' => true], 'options' => ['choices' => [['value' => 'normal', 'label' => 'Normal']]]],
                    ['type' => 'approver', 'name' => 'procurement_approver', 'label' => 'Procurement approver', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'finance_approver', 'label' => 'Finance approver', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createPolicyEnabledPoForm(): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Policy PO',
                'description' => 'Approval policy compiler test',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_order',
                    'use_approval_policy' => true,
                ],
                'fields' => [
                    ['type' => 'currency', 'name' => 'total_amount', 'label' => 'PO total', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'procurement_approver', 'label' => 'Procurement approver', 'validation' => ['required' => true]],
                    ['type' => 'approver', 'name' => 'finance_approver', 'label' => 'Finance approver', 'validation' => ['required' => true]],
                ],
                'steps' => [
                    ['type' => 'manager', 'step_order' => 1],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }
}
