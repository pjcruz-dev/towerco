<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantEnvironmentSwitchPermissionTest extends TestCase
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
            'toweros.environment_switch.enabled' => true,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_viewer_cannot_mint_environment_handoff(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions(['dashboard:view']);
        $blocked->syncRoles([]);
        tenancy()->end();

        $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/workspace/environments/handoff', [
                'environment' => 'production',
            ])
            ->assertForbidden();
    }

    public function test_tenant_admin_list_marks_handoff_supported(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/environments')
            ->assertOk()
            ->assertJsonPath('data.handoff_supported', true);
    }

    public function test_viewer_list_does_not_advertise_handoff(): void
    {
        $blocked = $this->testTenantAdmin;
        tenancy()->initialize($this->testTenant);
        $blocked->syncPermissions(['dashboard:view']);
        $blocked->syncRoles([]);
        tenancy()->end();

        $response = $this->actingAs($blocked, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/workspace/environments');

        $response->assertOk()->assertJsonPath('data.handoff_supported', true);

        foreach ($response->json('data.environments') as $environment) {
            $this->assertFalse($environment['handoff_available']);
        }
    }
}
