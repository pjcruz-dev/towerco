<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalFormWorkflowRulesCompilerTest extends TestCase
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

    public function test_conditional_workflow_steps_compile_department_mapping_and_amount_gate(): void
    {
        $networkHead = $this->createAdditionalTenantUser('network.head@towerone.test', 'Network Head');
        $cfoUser = $this->createAdditionalTenantUser('cfo@towerone.test', 'CFO User');

        $formId = $this->createConditionalWorkflowForm($networkHead, $cfoUser);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'Network capex request',
                    'department' => 'network',
                    'estimated_total' => '750000',
                ],
            ]);

        $response->assertCreated();

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $this->assertSame('conditional_steps', $workflow['workflow_mode'] ?? null);
        $this->assertCount(2, $workflow['steps']);
        $this->assertSame('field_map', $workflow['steps'][0]['approver_type']);
        $this->assertSame('department', $workflow['steps'][0]['approver_id']);
        $this->assertSame('user', $workflow['steps'][1]['approver_type']);
        $this->assertSame((string) $cfoUser->id, $workflow['steps'][1]['approver_id']);
    }

    public function test_conditional_workflow_skips_amount_gated_step_below_threshold(): void
    {
        $networkHead = $this->createAdditionalTenantUser('network.head@towerone.test', 'Network Head');
        $cfoUser = $this->createAdditionalTenantUser('cfo@towerone.test', 'CFO User');

        $formId = $this->createConditionalWorkflowForm($networkHead, $cfoUser);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'Small network request',
                    'department' => 'network',
                    'estimated_total' => '250000',
                ],
            ]);

        $response->assertCreated();

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $workflow = json_decode((string) $submission->workflow_snapshot_json, true, 512, JSON_THROW_ON_ERROR);
        tenancy()->end();

        $this->assertCount(1, $workflow['steps']);
        $this->assertSame('field_map', $workflow['steps'][0]['approver_type']);
    }

    public function test_form_workflow_preview_resolves_active_conditional_steps(): void
    {
        $networkHead = $this->createAdditionalTenantUser('network.head@towerone.test', 'Network Head');
        $cfoUser = $this->createAdditionalTenantUser('cfo@towerone.test', 'CFO User');

        $formId = $this->createConditionalWorkflowForm($networkHead, $cfoUser);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", [
                'values' => [
                    'department' => 'network',
                    'estimated_total' => '750000',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.workflow_mode', 'conditional_steps')
            ->assertJsonPath('data.resolved_steps.0.type', 'field_map')
            ->assertJsonPath('data.resolved_steps.0.resolved_user_id', (string) $networkHead->id)
            ->assertJsonPath('data.resolved_steps.1.type', 'user')
            ->assertJsonPath('data.resolved_steps.1.resolved_user_id', (string) $cfoUser->id);
    }

    public function test_form_workflow_preview_accepts_empty_values_payload(): void
    {
        $networkHead = $this->createAdditionalTenantUser('network.head@towerone.test', 'Network Head');
        $cfoUser = $this->createAdditionalTenantUser('cfo@towerone.test', 'CFO User');

        $formId = $this->createConditionalWorkflowForm($networkHead, $cfoUser);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", []);

        $response->assertOk()
            ->assertJsonPath('data.workflow_mode', 'conditional_steps');
    }

    private function createAdditionalTenantUser(string $email, string $name): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->givePermissionTo('e_approval:approve');
        tenancy()->end();

        return $user;
    }

    public function test_field_map_preview_resolves_label_mapping_key_with_code_submission_value(): void
    {
        $executiveHead = $this->createAdditionalTenantUser('executive.head@towerone.test', 'Executive Head');
        $financeHead = $this->createAdditionalTenantUser('finance.head@towerone.test', 'Finance Head');

        $formId = $this->createDepartmentFieldMapFormWithLabelKeys($executiveHead, $financeHead);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", [
                'values' => [
                    'department' => 'exec_admin',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.resolved_steps.0.type', 'field_map')
            ->assertJsonPath('data.resolved_steps.0.resolved_user_id', (string) $executiveHead->id)
            ->assertJsonPath('data.resolved_steps.0.mapping_matched_key', 'Executive / Administration');
    }

    public function test_field_map_submit_resolves_label_mapping_key_with_code_submission_value(): void
    {
        $executiveHead = $this->createAdditionalTenantUser('executive.head@towerone.test', 'Executive Head');
        $financeHead = $this->createAdditionalTenantUser('finance.head@towerone.test', 'Finance Head');

        $formId = $this->createDepartmentFieldMapFormWithLabelKeys($executiveHead, $financeHead);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'Executive request',
                    'department' => 'exec_admin',
                ],
            ]);

        $response->assertCreated();

        $submissionId = (string) $response->json('data.id');
        tenancy()->initialize($this->testTenant);
        $approval = EApprovalSubmission::query()
            ->findOrFail($submissionId)
            ->approvals()
            ->with('step')
            ->orderBy('created_at')
            ->first();
        tenancy()->end();

        $this->assertNotNull($approval);
        $this->assertSame((string) $executiveHead->id, (string) $approval->approver_id);
        $this->assertSame('field_map', (string) $approval->step?->approver_type);
    }

    private function createDepartmentFieldMapFormWithLabelKeys(TenantUser $executiveHead, TenantUser $financeHead): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Department field map',
                'description' => 'Label key mapping test',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Title', 'validation' => ['required' => true]],
                    [
                        'type' => 'select',
                        'name' => 'department',
                        'label' => 'Department',
                        'options' => [
                            'choices' => [
                                ['value' => 'exec_admin', 'label' => 'Executive / Administration'],
                                ['value' => 'finance', 'label' => 'Finance and Accounting'],
                            ],
                        ],
                    ],
                ],
                'steps' => [
                    [
                        'type' => 'field_map',
                        'source_field' => 'department',
                        'mappings' => [
                            'Executive / Administration' => (string) $executiveHead->id,
                            'Finance and Accounting' => (string) $financeHead->id,
                        ],
                        'step_order' => 1,
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }

    private function createConditionalWorkflowForm(TenantUser $networkHead, TenantUser $cfoUser): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Conditional workflow PR',
                'description' => 'Conditional steps test',
                'status' => 'published',
                'metadata_json' => [
                    'form_family' => 'purchase_requisition',
                ],
                'fields' => [
                    ['type' => 'text', 'name' => 'title', 'label' => 'Title', 'validation' => ['required' => true]],
                    [
                        'type' => 'select',
                        'name' => 'department',
                        'label' => 'Department',
                        'options' => [
                            'choices' => [
                                ['value' => 'network', 'label' => 'Network'],
                                ['value' => 'operations', 'label' => 'Operations'],
                            ],
                        ],
                    ],
                    ['type' => 'currency', 'name' => 'estimated_total', 'label' => 'Estimated total'],
                ],
                'steps' => [
                    [
                        'type' => 'field_map',
                        'source_field' => 'department',
                        'mappings' => [
                            'network' => (string) $networkHead->id,
                        ],
                        'step_order' => 1,
                        'when' => [
                            ['field' => 'department', 'operator' => 'equals', 'value' => 'network'],
                        ],
                    ],
                    [
                        'type' => 'user',
                        'approverId' => (string) $cfoUser->id,
                        'step_order' => 2,
                        'when' => [
                            ['field' => 'estimated_total', 'operator' => 'gt', 'value' => '500000'],
                        ],
                    ],
                ],
            ]);

        $response->assertCreated();

        return (string) $response->json('data.form.id');
    }
}
