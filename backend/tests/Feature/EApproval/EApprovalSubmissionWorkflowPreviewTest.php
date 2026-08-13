<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalSubmissionWorkflowPreviewTest extends TestCase
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

    public function test_admin_can_preview_submission_workflow_path_with_skipped_steps(): void
    {
        $networkHead = $this->createAdditionalTenantUser('network.head@towerone.test', 'Network Head');
        $cfoUser = $this->createAdditionalTenantUser('cfo@towerone.test', 'CFO User');
        $formId = $this->createConditionalWorkflowForm($networkHead, $cfoUser);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'title' => 'Small network request',
                    'department' => 'network',
                    'estimated_total' => '250000',
                ],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview");

        $preview->assertOk()
            ->assertJsonPath('data.definition_source', 'workflow_snapshot')
            ->assertJsonPath('data.resolved_steps.0.type', 'field_map')
            ->assertJsonPath('data.resolved_steps.0.resolved_user_id', (string) $networkHead->id)
            ->assertJsonPath('data.resolved_steps.0.runtime_status', 'pending');

        $this->assertCount(1, $preview->json('data.resolved_steps'));
        $this->assertNotEmpty($preview->json('data.skipped_steps'));
        $this->assertSame('user', $preview->json('data.skipped_steps.0.type'));
    }

    public function test_requestor_can_preview_submission_workflow_path(): void
    {
        $approver = $this->createAdditionalTenantUser('approver.path@towerone.test', 'Path Approver');
        $formId = $this->createSimpleForm($approver);
        $requestor = $this->createViewerUser('path.requestor@towerone.test', withCreate: true);

        $create = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Need access'],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview")
            ->assertOk()
            ->assertJsonPath('data.resolved_steps.0.runtime_status', 'pending');
    }

    public function test_cancelled_submission_workflow_path_shows_cancelled_not_pending(): void
    {
        Notification::fake();
        Mail::fake();

        $approver = $this->createAdditionalTenantUser('approver.cancel.path@towerone.test', 'Cancel Path Approver');
        $formId = $this->createSimpleForm($approver);
        $requestor = $this->createViewerUser('cancel.path.requestor@towerone.test', withCreate: true);

        $create = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Will cancel'],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/submissions/{$submissionId}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview")
            ->assertOk()
            ->assertJsonPath('data.resolved_steps.0.runtime_status', 'cancelled');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.viewer_pending_approval_id', null);
    }

    public function test_unrelated_viewer_cannot_preview_submission_workflow_path(): void
    {
        $approver = $this->createAdditionalTenantUser('approver.path2@towerone.test', 'Path Approver 2');
        $formId = $this->createSimpleForm($approver);

        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Need access'],
            ]);

        $create->assertCreated();
        $submissionId = (string) $create->json('data.id');

        $viewer = $this->createViewerUser('path.unrelated@towerone.test');

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/submissions/{$submissionId}/workflow-preview")
            ->assertUnprocessable();
    }

    private function createViewerUser(string $email = 'path.viewer@towerone.test', bool $withCreate = false): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => 'Path Viewer',
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $permissions = [
            'e_approval:view',
            'e_approval:submissions:view',
        ];
        if ($withCreate) {
            $permissions[] = 'e_approval:submissions:create';
        }
        $user->givePermissionTo($permissions);
        tenancy()->end();

        return $user;
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

    private function createSimpleForm(TenantUser $approver): string
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Simple path form',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $approver->id, 'step_order' => 1],
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
                'name' => 'Conditional workflow path preview',
                'status' => 'published',
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
