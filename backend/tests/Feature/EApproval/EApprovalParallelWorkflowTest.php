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

final class EApprovalParallelWorkflowTest extends TestCase
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
            'email' => 'legal@test.localhost',
            'password' => 'password',
        ]);
        $this->legal->assignRole('e_approval_approver');

        $this->finance = TenantUser::query()->create([
            'name' => 'Finance Approver',
            'email' => 'finance@test.localhost',
            'password' => 'password',
        ]);
        $this->finance->assignRole('e_approval_approver');

        $this->ops = TenantUser::query()->create([
            'name' => 'Ops Approver',
            'email' => 'ops@test.localhost',
            'password' => 'password',
        ]);
        $this->ops->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_parallel_same_step_order_waits_for_all_approvers(): void
    {
        $formId = $this->createParallelForm();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Need both'],
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

        $legalApproval = $pending->firstWhere('approver_id', (string) $this->legal->id);
        $financeApproval = $pending->firstWhere('approver_id', (string) $this->finance->id);
        $this->assertNotNull($legalApproval);
        $this->assertNotNull($financeApproval);

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertSame('pending', $submission->status);
        $this->assertSame(1, (int) $submission->current_step);

        $stillPending = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->count();
        $this->assertSame(1, $stillPending);

        $this->actingAs($this->finance, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$financeApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $submission->refresh();
        $this->assertSame('approved', $submission->status);
    }

    public function test_parallel_any_mode_advances_after_first_approval(): void
    {
        $formId = $this->createParallelForm(mode: 'any');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Any one'],
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

        $finance = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->finance->id)
            ->firstOrFail();

        $this->assertSame(EApprovalApprovalStatus::INVALIDATED, $finance->status);
        $this->assertSame('approved', EApprovalSubmission::query()->findOrFail($submissionId)->status);
    }

    public function test_parallel_n_of_m_advances_after_quorum(): void
    {
        $formId = $this->createParallelForm(
            mode: 'n_of_m',
            quorum: 2,
            members: [
                (string) $this->legal->id,
                (string) $this->finance->id,
                (string) $this->ops->id,
            ],
        );

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Quorum two of three'],
            ]);

        $subRes->assertCreated();
        $submissionId = $subRes->json('data.id');

        $this->assertSame(
            3,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->count(),
        );

        $legalApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->legal->id)
            ->firstOrFail();
        $financeApproval = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->finance->id)
            ->firstOrFail();

        $this->actingAs($this->legal, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$legalApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $submission = EApprovalSubmission::query()->findOrFail($submissionId);
        $this->assertSame('pending', $submission->status);
        $this->assertSame(
            2,
            EApprovalRequestApproval::query()
                ->where('submission_id', $submissionId)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->count(),
        );

        $this->actingAs($this->finance, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$financeApproval->id}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $ops = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->ops->id)
            ->firstOrFail();

        $this->assertSame(EApprovalApprovalStatus::INVALIDATED, $ops->status);
        $this->assertSame('approved', EApprovalSubmission::query()->findOrFail($submissionId)->status);
    }

    public function test_reject_invalidates_other_parallel_pending_approvals(): void
    {
        $formId = $this->createParallelForm();

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Reject path'],
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
                'decision' => 'rejected',
                'remarks' => 'Not approved',
            ])
            ->assertOk();

        $finance = EApprovalRequestApproval::query()
            ->where('submission_id', $submissionId)
            ->where('approver_id', (string) $this->finance->id)
            ->firstOrFail();

        $this->assertSame(EApprovalApprovalStatus::INVALIDATED, $finance->status);
        $this->assertSame('rejected', EApprovalSubmission::query()->findOrFail($submissionId)->status);
    }

    public function test_workflow_preview_preserves_parallel_step_order(): void
    {
        $formId = $this->createParallelForm();

        $preview = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/forms/{$formId}/workflow-preview", [
                'values' => ['reason' => 'x'],
            ]);

        $preview->assertOk();
        $resolved = $preview->json('data.resolved_steps');
        $this->assertCount(2, $resolved);
        $this->assertSame(1, (int) $resolved[0]['step_order']);
        $this->assertSame(1, (int) $resolved[1]['step_order']);
    }

    public function test_form_detail_returns_parallel_mode_and_quorum(): void
    {
        $formId = $this->createParallelForm(mode: 'n_of_m', quorum: 2, members: [
            (string) $this->legal->id,
            (string) $this->finance->id,
            (string) $this->ops->id,
        ]);

        $detail = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson("/api/v1/e-approval/forms/{$formId}");

        $detail->assertOk();
        $steps = $detail->json('data.steps');
        $this->assertCount(3, $steps);
        foreach ($steps as $step) {
            $this->assertSame('n_of_m', $step['parallel_mode'] ?? null);
            $this->assertSame(2, (int) ($step['parallel_quorum'] ?? 0));
            $this->assertSame(1, (int) $step['step_order']);
        }
    }

    /**
     * @param  list<string>|null  $members
     */
    private function createParallelForm(
        string $mode = 'all',
        ?int $quorum = null,
        ?array $members = null,
    ): string {
        $approverIds = $members ?? [
            (string) $this->legal->id,
            (string) $this->finance->id,
        ];

        $steps = [];
        foreach ($approverIds as $approverId) {
            $step = [
                'type' => 'user',
                'approverId' => $approverId,
                'step_order' => 1,
            ];
            if ($mode !== 'all') {
                $step['parallel_mode'] = $mode;
            }
            if ($mode === 'n_of_m') {
                $step['parallel_quorum'] = $quorum ?? 1;
            }
            $steps[] = $step;
        }

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Parallel Legal Finance',
                'description' => 'Parallel band',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
                ],
                'steps' => $steps,
            ]);

        $formRes->assertCreated();

        return (string) $formRes->json('data.form.id');
    }
}
