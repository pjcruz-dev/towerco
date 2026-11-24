<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Models\EApprovalAuditLog;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantWorkspaceDashboardTest extends TestCase
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

    public function test_dashboard_returns_operational_payload(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'environment',
                    'kpis',
                    'actions',
                    'recent_activity',
                    'quick_links',
                ],
            ]);
    }

    public function test_dashboard_requires_permission(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions([]);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dashboard')
            ->assertForbidden();
    }

    public function test_recent_activity_excludes_tenant_wide_admin_audit_logs(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = $this->testTenantAdmin;
        $requestor->syncRoles(['e_approval_requestor']);

        EApprovalAuditLog::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'action' => 'form_deleted',
            'target_id' => (string) Str::uuid(),
            'remarks' => 'Procurement form cleanup',
        ]);

        tenancy()->end();

        $response = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dashboard');

        $response->assertOk();

        $labels = collect($response->json('data.recent_activity'))
            ->pluck('label')
            ->all();

        $this->assertNotContains('form_deleted', $labels);
        $this->assertNotContains('form_created', $labels);
    }

    public function test_requestor_dashboard_counts_only_their_own_submissions(): void
    {
        tenancy()->initialize($this->testTenant);

        $requestor = TenantUser::query()->create([
            'name' => 'Alger Requestor',
            'email' => 'alger@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $requestor->assignRole('e_approval_requestor');

        $form = EApprovalForm::query()->create([
            'name' => 'Cash Advance',
            'status' => 'published',
            'schema_version' => 1,
        ]);
        $template = EApprovalWorkflowTemplate::query()->create([
            'form_id' => $form->id,
        ]);
        $step = EApprovalWorkflowStep::query()->create([
            'template_id' => $template->id,
            'step_order' => 1,
            'approver_type' => 'user',
            'approver_id' => (string) $this->testTenantAdmin->id,
        ]);
        $submission = EApprovalSubmission::query()->create([
            'document_no' => 'OTHER-1',
            'form_id' => $form->id,
            'requestor_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
            'current_step' => 1,
        ]);
        $approval = EApprovalRequestApproval::query()->create([
            'submission_id' => $submission->id,
            'step_id' => $step->id,
            'approver_id' => $this->testTenantAdmin->id,
            'status' => 'pending',
        ]);
        $approval->forceFill([
            'created_at' => Carbon::now()->subDays(10),
        ])->save();

        tenancy()->end();

        $response = $this->actingAs($requestor, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/dashboard');

        $response->assertOk();
        $keys = collect($response->json('data.kpis'))->pluck('key')->all();
        $this->assertNotContains('ea_open_submissions', $keys);
        $this->assertNotContains('ea_stale_approvals', $keys);
    }
}
