<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class RoleCatalogTest extends TestCase
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

    public function test_role_catalog_includes_user_count(): void
    {
        $user = $this->createTenantUser('assigned@towerone.test', 'Assigned User');
        tenancy()->initialize($this->testTenant);
        $user->syncRoles(['viewer']);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles');

        $response->assertOk();

        $viewer = collect($response->json('data.roles'))->firstWhere('name', 'viewer');
        $this->assertNotNull($viewer);
        $this->assertGreaterThanOrEqual(1, (int) ($viewer['user_count'] ?? 0));
    }

    public function test_role_show_lists_assigned_users(): void
    {
        $user = $this->createTenantUser('show.user@towerone.test', 'Show User');
        tenancy()->initialize($this->testTenant);
        $user->syncRoles(['viewer']);
        $role = TenantRole::query()->where('name', 'viewer')->firstOrFail();
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles/'.$role->id);

        $response->assertOk()
            ->assertJsonPath('data.name', 'viewer')
            ->assertJsonPath('data.users.0.email', 'show.user@towerone.test');
    }

    public function test_baseline_role_can_be_cloned_as_custom_role(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/roles/'.$this->viewerRoleId().'/clone', [
                'name' => 'viewer_ops_copy',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'viewer_ops_copy')
            ->assertJsonPath('data.is_baseline', false);

        $this->assertNotEmpty($response->json('data.permissions'));
    }

    public function test_custom_role_cannot_be_deleted_while_assigned(): void
    {
        $roleId = $this->createCustomRole('field_supervisor');

        $user = $this->createTenantUser('field@towerone.test', 'Field User');
        tenancy()->initialize($this->testTenant);
        $user->syncRoles(['field_supervisor']);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/admin/roles/'.$roleId);

        $response->assertStatus(422);
    }

    public function test_custom_role_can_be_deleted_when_unassigned(): void
    {
        $roleId = $this->createCustomRole('unused_role');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/admin/roles/'.$roleId);

        $response->assertOk()->assertJsonPath('data.deleted', true);
    }

    public function test_baseline_role_cannot_be_deleted(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/admin/roles/'.$this->viewerRoleId());

        $response->assertStatus(422);
    }

    public function test_role_catalog_hides_administrator_alias(): void
    {
        tenancy()->initialize($this->testTenant);
        TenantRole::query()->firstOrCreate([
            'name' => 'administrator',
            'guard_name' => 'sanctum',
        ]);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles');

        $response->assertOk();
        $names = collect($response->json('data.roles'))->pluck('name')->all();
        $this->assertContains('tenant_admin', $names);
        $this->assertNotContains('administrator', $names);
    }

    public function test_administrator_assignments_fold_onto_tenant_admin(): void
    {
        $user = $this->createTenantUser('alias@towerone.test', 'Alias Admin');
        tenancy()->initialize($this->testTenant);
        TenantRole::query()->firstOrCreate([
            'name' => 'administrator',
            'guard_name' => 'sanctum',
        ]);
        $user->syncRoles(['administrator']);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles')
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->hasRole('tenant_admin'));
        $this->assertFalse($fresh->hasRole('administrator'));
        tenancy()->end();
    }

    public function test_system_role_permission_toggles_persist_through_baseline_ensure(): void
    {
        $roleId = $this->viewerRoleId();
        $catalog = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles');
        $catalog->assertOk();

        $permissions = $catalog->json('data.permissions');
        $viewer = collect($catalog->json('data.roles'))->firstWhere('name', 'viewer');
        $this->assertIsArray($permissions);
        $this->assertIsArray($viewer);
        $current = $viewer['permissions'];
        $this->assertIsArray($current);
        $extra = collect($permissions)->first(
            static fn (mixed $name): bool => is_string($name) && ! in_array($name, $current, true),
        );
        $this->assertIsString($extra);

        $next = ['dashboard:view', $extra];
        $update = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/admin/roles/'.$roleId, [
                'permissions' => $next,
            ]);

        $update->assertOk();
        $this->assertEqualsCanonicalizing($next, $update->json('data.permissions'));

        tenancy()->initialize($this->testTenant);
        app(TenantRbacBaselineService::class)->ensure();
        tenancy()->end();

        $again = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles/'.$roleId);

        $again->assertOk();
        $this->assertEqualsCanonicalizing($next, $again->json('data.permissions'));
    }

    public function test_workspace_owner_permissions_cannot_be_narrowed(): void
    {
        tenancy()->initialize($this->testTenant);
        $id = (int) TenantRole::query()->where('name', 'tenant_admin')->value('id');
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/admin/roles/'.$id, [
                'permissions' => ['dashboard:view'],
            ]);

        $response->assertStatus(422);
    }

    public function test_owner_alias_cannot_be_created_as_custom_role(): void
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/roles', [
                'name' => 'administrator',
                'permissions' => ['dashboard:view'],
            ]);

        $response->assertStatus(422);
    }

    private function createTenantUser(string $email, string $name): TenantUser
    {
        tenancy()->initialize($this->testTenant);
        $user = TenantUser::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'password',
            'is_active' => true,
        ]);
        tenancy()->end();

        return $user;
    }

    private function viewerRoleId(): int
    {
        tenancy()->initialize($this->testTenant);
        $id = (int) TenantRole::query()->where('name', 'viewer')->value('id');
        tenancy()->end();

        return $id;
    }

    private function createCustomRole(string $name): int
    {
        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/roles', [
                'name' => $name,
                'permissions' => ['dashboard:view'],
            ]);

        $response->assertCreated();

        return (int) $response->json('data.id');
    }
}
