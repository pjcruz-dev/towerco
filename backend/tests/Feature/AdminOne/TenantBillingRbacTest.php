<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantBillingRbacTest extends TestCase
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

    public function test_billing_is_core_baseline_role_with_dedicated_permissions(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles');

        $response->assertOk();

        $billing = collect($response->json('data.roles'))->firstWhere('name', 'billing');
        $this->assertNotNull($billing);
        $this->assertTrue((bool) ($billing['is_baseline'] ?? false));
        $this->assertTrue((bool) ($billing['is_system'] ?? false));
        $this->assertContains('billing:view', $billing['permissions'] ?? []);
        $this->assertContains('billing:manage', $billing['permissions'] ?? []);
        $this->assertNotContains('tenant:manage', $billing['permissions'] ?? []);
        $this->assertNotContains('user:manage', $billing['permissions'] ?? []);
        $this->assertNotContains('role:manage', $billing['permissions'] ?? []);
    }

    public function test_billing_role_can_view_billing_but_not_tenant_settings(): void
    {
        $billingUser = $this->createRoleUser('billing.ops@towerone.test', 'Billing Ops', 'billing');

        $this->actingAs($billingUser, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/billing')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $this->testTenant->id);

        $this->actingAs($billingUser, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/billing/usage')
            ->assertOk();

        $this->actingAs($billingUser, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/settings')
            ->assertForbidden();
    }

    public function test_viewer_cannot_access_billing(): void
    {
        $viewer = $this->createRoleUser('viewer@towerone.test', 'Viewer User', 'viewer');

        $this->actingAs($viewer, 'sanctum')
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/billing')
            ->assertForbidden();
    }

    public function test_tenant_admin_still_can_access_billing(): void
    {
        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/billing')
            ->assertOk();
    }

    private function createRoleUser(string $email, string $name, string $role): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->syncRoles([$role]);
        tenancy()->end();

        return $user;
    }
}
