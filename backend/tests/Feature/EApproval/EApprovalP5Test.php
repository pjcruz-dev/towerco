<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalP5Test extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    private TenantUser $approver;

    private TenantUser $alternateApprover;

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
            'email' => 'approver-p5@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->approver->assignRole('e_approval_approver');

        $this->alternateApprover = TenantUser::query()->create([
            'name' => 'Alternate Approver',
            'email' => 'alternate-p5@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $this->alternateApprover->assignRole('e_approval_approver');
        tenancy()->end();
    }

    public function test_admin_can_reroute_pending_approval(): void
    {
        $formRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/forms', [
                'name' => 'Reroute Test',
                'status' => 'published',
                'fields' => [
                    ['type' => 'text', 'name' => 'summary', 'label' => 'Summary'],
                ],
                'steps' => [
                    ['type' => 'user', 'approverId' => (string) $this->approver->id, 'step_order' => 1],
                ],
            ]);

        $formRes->assertCreated();
        $formId = $formRes->json('data.form.id');

        $subRes = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/e-approval/submissions', [
                'form_id' => $formId,
                'values' => ['summary' => 'Needs reroute'],
            ]);

        $subRes->assertCreated();

        $approval = EApprovalRequestApproval::query()
            ->where('approver_id', $this->approver->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($approval);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson("/api/v1/e-approval/approvals/{$approval->id}/reroute", [
                'new_approver_id' => (string) $this->alternateApprover->id,
                'reason' => 'Original approver is out of office',
            ])
            ->assertOk()
            ->assertJsonPath('data.approver_id', (string) $this->alternateApprover->id);

        $approval->refresh();
        $this->assertSame((string) $this->alternateApprover->id, (string) $approval->approver_id);
    }
}
