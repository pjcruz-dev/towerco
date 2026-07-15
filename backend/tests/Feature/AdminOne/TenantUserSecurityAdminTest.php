<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserSecurityAdminTest extends TestCase
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

    public function test_admin_can_revoke_all_sessions_for_user(): void
    {
        $target = $this->createTenantUser('target.user@towerone.test', 'Target User');
        $this->seedSession($target, 'local', now()->subHour());
        $this->seedAccessToken($target);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/admin/users/'.$target->id.'/revoke-sessions');

        $response->assertOk();

        tenancy()->initialize($this->testTenant);
        $this->assertSame(
            0,
            DB::table('auth_sessions')
                ->where('user_id', $target->id)
                ->whereNull('revoked_at')
                ->where('state', 'active')
                ->count(),
        );
        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $target->id,
            'event' => 'auth.admin.sessions_revoked',
        ]);
        tenancy()->end();
    }

    public function test_user_activity_returns_auth_audit_events(): void
    {
        $target = $this->createTenantUser('audit.user@towerone.test', 'Audit User');

        tenancy()->initialize($this->testTenant);
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

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users/'.$target->id.'/activity');

        $response->assertOk()
            ->assertJsonPath('data.0.event', 'auth.login.success')
            ->assertJsonPath('data.0.label', 'Signed in');
    }

    public function test_tenant_admin_can_update_mfa_policy(): void
    {
        config(['toweros.tenant_mfa.global_required' => true]);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->patchJson('/api/v1/admin/security', ['mfa_required' => true]);

        $response->assertOk()
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonPath('data.mfa_policy_active', true);

        $this->testTenant->refresh();
        $this->assertTrue((bool) $this->testTenant->mfa_required);
    }

    public function test_user_index_filters_by_role(): void
    {
        $viewer = $this->createTenantUser('viewer.user@towerone.test', 'Viewer User');
        $manager = $this->createTenantUser('manager.user@towerone.test', 'Manager User');
        tenancy()->initialize($this->testTenant);
        $manager->syncRoles(['manager']);
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?role=manager&per_page=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $manager->id, $ids);
        $this->assertNotContains((string) $viewer->id, $ids);
    }

    public function test_role_compare_returns_permission_diff(): void
    {
        tenancy()->initialize($this->testTenant);
        $viewer = TenantRole::query()->where('name', 'viewer')->firstOrFail();
        $manager = TenantRole::query()->where('name', 'manager')->firstOrFail();
        tenancy()->end();

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/roles/compare?left='.$viewer->id.'&right='.$manager->id);

        $response->assertOk()
            ->assertJsonPath('data.left.name', 'viewer')
            ->assertJsonPath('data.right.name', 'manager');

        $onlyRight = $response->json('data.only_right');
        $this->assertIsArray($onlyRight);
        $this->assertNotEmpty($onlyRight);
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

    private function seedSession(TenantUser $user, string $authMethod, \DateTimeInterface $lastSeen): void
    {
        tenancy()->initialize($this->testTenant);
        DB::table('auth_sessions')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'auth_method' => $authMethod,
            'state' => 'active',
            'last_seen_at' => $lastSeen,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();
    }

    private function seedAccessToken(TenantUser $user): void
    {
        tenancy()->initialize($this->testTenant);
        Sanctum::actingAs($user, ['*'], 'sanctum');
        $user->createToken('test-token');
        tenancy()->end();
    }
}
