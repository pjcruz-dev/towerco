<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalApprovalsIndexTest extends TestCase
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
            'email' => 'approver-index@test.localhost',
            'password' => 'password',
        ]);
        $this->approver->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_approvals_index_all_filter_returns_completed_step_for_approver(): void
    {
        $formPayload = [
            'name' => 'Leave Request',
            'description' => 'Index test',
            'status' => 'published',
            'fields' => [
                ['type' => 'text', 'name' => 'reason', 'label' => 'Reason'],
            ],
            'steps' => [
                ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
            ],
        ];

        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', $formPayload);

        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['reason' => 'Annual leave'],
            ]);

        $submissionId = $subRes->json('data.id');

        $inbox = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?awaiting_me=1');

        $approvalId = $inbox->json('data.0.id');
        $this->assertNotEmpty($approvalId);

        $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approvalId}/decide", [
                'decision' => 'approved',
            ])
            ->assertOk();

        $all = $this->actingAs($this->approver, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/approvals?status=all&page=1&per_page=25');

        $all->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.approval_status', 'approved')
            ->assertJsonPath('data.0.submission.id', $submissionId);
    }
}
