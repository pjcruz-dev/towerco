<?php

declare(strict_types=1);

namespace Tests\Feature\Workspace;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\EApproval\Services\EApprovalAuditLogger;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class WorkspaceAuditTest extends TestCase
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
                'e_approval',
            ],
        ]);

        $this->bootInMemoryTenantApi();
        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();
    }

    public function test_workspace_audit_requires_permission(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions(['dashboard:view']);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit')
            ->assertForbidden();
    }

    public function test_workspace_audit_lists_dual_written_e_approval_events(): void
    {
        tenancy()->initialize($this->testTenant);
        app(EApprovalAuditLogger::class)->log(
            'submission_manual_follow_up',
            'sub-123',
            'Reminder sent',
            $this->testTenantAdmin,
        );
        $this->assertSame(1, \App\Modules\EApproval\Models\EApprovalAuditLog::query()->count());
        $this->assertSame(1, \App\Modules\Workspace\Models\TenantActivityLog::query()->count());
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/audit')
            ->assertOk()
            ->assertJsonFragment(['source' => 'workspace', 'action' => 'submission_manual_follow_up'])
            ->assertJsonFragment(['source' => 'e_approval', 'action' => 'submission_manual_follow_up']);
    }
}
