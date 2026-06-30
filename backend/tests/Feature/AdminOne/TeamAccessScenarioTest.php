<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

/**
 * End-to-end Team & Access scenarios — user lifecycle, roles, and security admin in sequence.
 */
#[Group('scenario')]
#[Group('team-access')]
final class TeamAccessScenarioTest extends TestCase
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

    public function test_scenario_user_lifecycle_create_filter_bulk_role_and_deactivate(): void
    {
        $create = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users', [
                'name' => 'Scenario Ops',
                'email' => 'scenario.ops@towerone.test',
                'roles' => ['viewer'],
            ]);

        $create->assertCreated()
            ->assertJsonPath('data.email', 'scenario.ops@towerone.test')
            ->assertJsonPath('data.roles.0', 'viewer');

        $userId = (string) $create->json('data.id');
        $this->assertNotEmpty($create->json('data.generated_password'));

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=scenario.ops@towerone.test')
            ->assertOk()
            ->assertJsonPath('data.0.id', $userId)
            ->assertJsonPath('data.0.is_active', true);

        $bulkRole = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-assign-role', [
                'user_ids' => [$userId],
                'role' => 'manager',
            ]);

        $bulkRole->assertOk()
            ->assertJsonPath('data.processed', 1);

        tenancy()->initialize($this->testTenant);
        $this->assertTrue(TenantUser::query()->findOrFail($userId)->hasRole('manager'));
        tenancy()->end();

        $managerIndex = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?role=manager&per_page=50');

        $managerIndex->assertOk();
        $managerIds = collect($managerIndex->json('data'))->pluck('id')->all();
        $this->assertContains($userId, $managerIds);

        $bulkDeactivate = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/bulk-deactivate', [
                'user_ids' => [$userId],
            ]);

        $bulkDeactivate->assertOk()
            ->assertJsonPath('data.processed', 1);

        tenancy()->initialize($this->testTenant);
        $this->assertFalse(TenantUser::query()->findOrFail($userId)->isActive());
        tenancy()->end();
    }

    public function test_scenario_role_lifecycle_clone_compare_and_delete(): void
    {
        tenancy()->initialize($this->testTenant);
        $viewer = TenantRole::query()->where('name', 'viewer')->firstOrFail();
        $manager = TenantRole::query()->where('name', 'manager')->firstOrFail();
        tenancy()->end();

        $clone = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/roles/'.$viewer->id.'/clone', [
                'name' => 'scenario_viewer_clone',
            ]);

        $clone->assertCreated()
            ->assertJsonPath('data.name', 'scenario_viewer_clone')
            ->assertJsonPath('data.is_baseline', false);

        $clonedRoleId = (int) $clone->json('data.id');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles/compare?left='.$viewer->id.'&right='.$manager->id)
            ->assertOk()
            ->assertJsonPath('data.left.name', 'viewer')
            ->assertJsonPath('data.right.name', 'manager');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/admin/roles/'.$clonedRoleId)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson('/api/v1/admin/roles/'.$viewer->id)
            ->assertStatus(422);
    }

    public function test_scenario_security_admin_mfa_activity_and_revoke_sessions(): void
    {
        config(['toweros.tenant_mfa.global_required' => true]);

        $target = $this->createTenantUser('scenario.security@towerone.test', 'Scenario Security');

        tenancy()->initialize($this->testTenant);
        DB::table('auth_sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $target->id,
            'auth_method' => 'local',
            'state' => 'active',
            'last_seen_at' => now()->subMinutes(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('auth_audit_logs')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $target->id,
            'session_id' => null,
            'event' => 'auth.login.success',
            'risk_level' => 'low',
            'ip_address' => '127.0.0.1',
            'context' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/admin/security', ['mfa_required' => true])
            ->assertOk()
            ->assertJsonPath('data.mfa_policy_active', true);

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users/'.$target->id.'/activity')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'auth.login.success');

        $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/'.$target->id.'/revoke-sessions')
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $this->assertSame(
            0,
            DB::table('auth_sessions')
                ->where('user_id', $target->id)
                ->whereNull('revoked_at')
                ->where('state', 'active')
                ->count(),
        );
        tenancy()->end();
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
