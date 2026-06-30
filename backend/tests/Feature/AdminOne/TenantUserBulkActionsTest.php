<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserBulkActionsTest extends TestCase
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

    public function test_bulk_deactivate_deactivates_multiple_users(): void
    {
        $first = $this->createTenantUser('bulk.one@towerone.test', 'Bulk One');
        $second = $this->createTenantUser('bulk.two@towerone.test', 'Bulk Two');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-deactivate', [
                'user_ids' => [(string) $first->id, (string) $second->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.errors', []);

        tenancy()->initialize($this->testTenant);
        $this->assertFalse($first->fresh()->isActive());
        $this->assertFalse($second->fresh()->isActive());
        tenancy()->end();
    }

    public function test_bulk_deactivate_skips_self_and_last_tenant_admin(): void
    {
        $target = $this->createTenantUser('bulk.target@towerone.test', 'Bulk Target');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-deactivate', [
                'user_ids' => [(string) $this->testTenantAdmin->id, (string) $target->id],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 1)
            ->assertJsonPath('data.skipped', 0);

        $errors = $response->json('data.errors');
        $this->assertIsArray($errors);
        $this->assertCount(1, $errors);
        $this->assertSame((string) $this->testTenantAdmin->id, $errors[0]['user_id']);

        tenancy()->initialize($this->testTenant);
        $this->assertTrue($this->testTenantAdmin->fresh()->isActive());
        $this->assertFalse($target->fresh()->isActive());
        tenancy()->end();
    }

    public function test_bulk_assign_roles_adds_multiple_roles_and_removes_viewer(): void
    {
        $first = $this->createTenantUser('roles.one@towerone.test', 'Roles One');
        $second = $this->createTenantUser('roles.two@towerone.test', 'Roles Two');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-assign-role', [
                'user_ids' => [(string) $first->id, (string) $second->id],
                'roles' => ['e_approval_requestor', 'ticketing_contributor'],
                'remove_roles' => ['viewer'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.errors', []);

        tenancy()->initialize($this->testTenant);
        $firstFresh = $first->fresh();
        $this->assertTrue($firstFresh->hasRole('e_approval_requestor'));
        $this->assertTrue($firstFresh->hasRole('ticketing_contributor'));
        $this->assertFalse($firstFresh->hasRole('viewer'));
        tenancy()->end();
    }

    public function test_bulk_assign_role_adds_role_to_selected_users(): void
    {
        $first = $this->createTenantUser('role.one@towerone.test', 'Role One');
        $second = $this->createTenantUser('role.two@towerone.test', 'Role Two');

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-assign-role', [
                'user_ids' => [(string) $first->id, (string) $second->id],
                'role' => 'manager',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 2)
            ->assertJsonPath('data.skipped', 0)
            ->assertJsonPath('data.errors', []);

        tenancy()->initialize($this->testTenant);
        $this->assertTrue($first->fresh()->hasRole('manager'));
        $this->assertTrue($second->fresh()->hasRole('manager'));
        $this->assertTrue($first->fresh()->hasRole('viewer'));
        tenancy()->end();
    }

    public function test_bulk_assign_role_skips_users_who_already_have_role(): void
    {
        $target = $this->createTenantUser('role.skip@towerone.test', 'Role Skip');
        tenancy()->initialize($this->testTenant);
        $target->assignRole('manager');
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-assign-role', [
                'user_ids' => [(string) $target->id],
                'role' => 'manager',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 0)
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.errors', []);
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
        $user->assignRole('viewer');
        tenancy()->end();

        return $user;
    }
}
