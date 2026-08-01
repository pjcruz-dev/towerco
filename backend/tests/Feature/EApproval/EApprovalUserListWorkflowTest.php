<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalUserListWorkflowTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $legal;

    private TenantUser $finance;

    private TenantUser $ops;

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
        $this->legal = TenantUser::query()->create([
            'name' => 'Legal Approver',
            'email' => 'legal-list@test.localhost',
            'password' => 'password',
        ]);
        $this->legal->assignRole('e_approval_approver');

        $this->finance = TenantUser::query()->create([
            'name' => 'Finance Approver',
            'email' => 'finance-list@test.localhost',
            'password' => 'password',
        ]);
        $this->finance->assignRole('e_approval_approver');

        $this->ops = TenantUser::query()->create([
            'name' => 'Ops Approver',
            'email' => 'ops-list@test.localhost',
            'password' => 'password',
        ]);
        $this->ops->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_user_list_expands_into_parallel_band_all_must_approve(): void
    {
        $formId = $this->createUserListForm(mode: 'all');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'stakeholders' => json_encode([
                        (string) $this->legal->id,
                        (string) $this->finance->id,
                    ], JSON_THROW_ON_ERROR),
                    'reason' => 'Stakeholder review',
                ],
            ]);

        $subRes->assertCreated();
        $submissionId = $subRes->json('data.id');

        $pending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->with('step')
            ->get();

        $this->assertCount(2, $pending);
        $this->assertTrue($pending->every(static fn ($row) => (int) $row->step?->step_order === 1));
        $this->assertNotNull($pending->firstWhere('approver_id', (string) $this->legal->id));
        $this->assertNotNull($pending->firstWhere('approver_id', (string) $this->finance->id));

        $legalApproval = $pending->firstWhere('approver_id', (string) $this->legal->id);
        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $this->assertSame('pending', EApprovalSubmission::query()->findOrFail($submissionId)->status);

        $financeApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->finance->id)
            ->firstOrFail();

        $this->actingAs($this->finance, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$financeApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $this->assertSame('approved', EApprovalSubmission::query()->findOrFail($submissionId)->status);
    }

    public function test_user_list_any_mode_settles_after_first_approval(): void
    {
        $formId = $this->createUserListForm(mode: 'any');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'stakeholders' => [
                        (string) $this->legal->id,
                        (string) $this->finance->id,
                        (string) $this->ops->id,
                    ],
                    'reason' => 'Any stakeholder',
                ],
            ]);

        $subRes->assertCreated();
        $submissionId = $subRes->json('data.id');

        $legalApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->legal->id)
            ->firstOrFail();

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $this->assertSame('approved', EApprovalSubmission::query()->findOrFail($submissionId)->status);
        $this->assertSame(
            2,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('status', EApprovalApprovalStatus::INVALIDATED)
                ->count(),
        );
    }

    public function test_user_list_empty_without_fallback_fails_submit(): void
    {
        $formId = $this->createUserListForm(mode: 'all');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => [
                    'stakeholders' => '[]',
                    'reason' => 'Missing list',
                ],
            ])
            ->assertStatus(422);
    }

    public function test_workflow_preview_expands_user_list(): void
    {
        $formId = $this->createUserListForm(mode: 'n_of_m', quorum: 2);

        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", [
                'values' => [
                    'stakeholders' => json_encode([
                        (string) $this->legal->id,
                        (string) $this->finance->id,
                        (string) $this->ops->id,
                    ], JSON_THROW_ON_ERROR),
                ],
            ]);

        $preview->assertOk();
        $resolved = $preview->json('data.resolved_steps');
        $this->assertCount(3, $resolved);
        $this->assertSame(1, (int) $resolved[0]['step_order']);
        $this->assertSame(1, (int) $resolved[1]['step_order']);
        $this->assertSame(1, (int) $resolved[2]['step_order']);
    }

    private function createUserListForm(string $mode = 'all', ?int $quorum = null): string
    {
        $step = [
            'type' => 'user_list',
            'approverId' => 'stakeholders',
            'step_order' => 1,
        ];
        if ($mode !== 'all') {
            $step['parallel_mode'] = $mode;
        }
        if ($mode === 'n_of_m') {
            $step['parallel_quorum'] = $quorum ?? 1;
        }

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Dynamic Stakeholder List',
                'description' => 'Expand list to parallel band',
                'status' => 'published',
                'fields' => [
                    ['type' => 'approver_list', 'name' => 'stakeholders', 'label' => 'Stakeholders'],
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => [$step],
            ]);

        $formRes->assertCreated();

        return (string) $formRes->json('data.form.id');
    }
}
