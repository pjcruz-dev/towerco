<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
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

        \App\Modules\EApproval\Models\EApprovalAuditLog::query()->create([
            'user_id' => $this->testTenantAdmin->id,
            'action' => 'form_deleted',
            'target_id' => (string) \Illuminate\Support\Str::uuid(),
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
}
