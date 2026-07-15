<?php

declare(strict_types=1);

namespace Tests\Feature\EApproval;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class EApprovalModuleShellTest extends TestCase
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

    public function test_health_reports_schema_ready(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/health');

        $response->assertOk()
            ->assertJsonPath('data.module', 'e-approval')
            ->assertJsonPath('data.schema_ready', true)
            ->assertJsonPath('data.status', 'ready');
    }

    public function test_dashboard_returns_p0_payload(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.phase', 'P7')
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'actions',
                    'message',
                ],
            ]);
    }

    public function test_dashboard_requires_permission(): void
    {
        $viewer = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $viewer->syncRoles(['e_approval_viewer']);
        tenancy()->end();

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/dashboard')
            ->assertOk();

        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions([]);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/e-approval/dashboard')
            ->assertForbidden();
    }
}
