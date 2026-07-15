<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

class TenantUserImpersonationTest extends TestCase
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
        config(['toweros.tenant_impersonation.enabled' => true]);
    }

    public function test_tenant_admin_can_impersonate_non_admin_user(): void
    {
        tenancy()->initialize($this->testTenant);

        $target = TenantUser::query()->create([
            'name' => 'Field User',
            'email' => 'field@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $target->assignRole('viewer');

        Sanctum::actingAs($this->testTenantAdmin, ['*', 'session:'.(string) Str::uuid()]);

        $response = $this->postJson(
            '/api/v1/admin/users/'.$target->id.'/impersonate',
            ['reason' => 'Support ticket #42'],
            $this->tenantApiHeaders(),
        );

        $response->assertOk()
            ->assertJsonPath('data.user.is_impersonating', true)
            ->assertJsonPath('data.user.impersonator.email', 'admin@test.localhost')
            ->assertJsonPath('data.user.email', 'field@test.localhost');

        $this->assertNotEmpty($response->json('data.access_token'));

        tenancy()->end();
    }

    public function test_cannot_impersonate_tenant_admin(): void
    {
        tenancy()->initialize($this->testTenant);

        $otherAdmin = TenantUser::query()->create([
            'name' => 'Other Admin',
            'email' => 'other-admin@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $otherAdmin->assignRole('tenant_admin');

        Sanctum::actingAs($this->testTenantAdmin, ['*']);

        $response = $this->postJson(
            '/api/v1/admin/users/'.$otherAdmin->id.'/impersonate',
            ['reason' => 'Test'],
            $this->tenantApiHeaders(),
        );

        $response->assertStatus(422);
        tenancy()->end();
    }

    public function test_manager_without_impersonate_permission_is_forbidden(): void
    {
        tenancy()->initialize($this->testTenant);

        $manager = TenantUser::query()->create([
            'name' => 'Manager',
            'email' => 'manager@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $manager->assignRole('manager');

        $target = TenantUser::query()->create([
            'name' => 'Viewer',
            'email' => 'viewer@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $target->assignRole('viewer');

        Sanctum::actingAs($manager, ['*']);

        $response = $this->postJson(
            '/api/v1/admin/users/'.$target->id.'/impersonate',
            ['reason' => 'Test'],
            $this->tenantApiHeaders(),
        );

        $response->assertForbidden();
        tenancy()->end();
    }

    public function test_stop_impersonation_revokes_session(): void
    {
        tenancy()->initialize($this->testTenant);

        $target = TenantUser::query()->create([
            'name' => 'Field User',
            'email' => 'field2@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $target->assignRole('viewer');

        Sanctum::actingAs($this->testTenantAdmin, ['*']);

        $start = $this->postJson(
            '/api/v1/admin/users/'.$target->id.'/impersonate',
            ['reason' => 'Debug workflow'],
            $this->tenantApiHeaders(),
        )->assertOk();

        $sessionId = $start->json('data.session_id');
        $token = $start->json('data.access_token');

        auth()->forgetGuards();

        $stop = $this->postJson('/api/v1/auth/impersonation/stop', [], array_merge($this->tenantApiHeaders(), [
            'Authorization' => 'Bearer '.$token,
            'X-Session-Id' => $sessionId,
        ]));

        $stop->assertOk();

        $session = DB::connection('tenant')->table('auth_sessions')->where('id', $sessionId)->first();
        $this->assertSame('revoked', $session->state);

        tenancy()->end();
    }
}
