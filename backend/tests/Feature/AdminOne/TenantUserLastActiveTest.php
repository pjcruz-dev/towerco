<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Services\AuthSessionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserLastActiveTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureMfaVerified::class,
        ]);

        $this->bootInMemoryTenantApi();
    }

    public function test_microsoft_session_last_seen_updates_on_authenticated_request(): void
    {
        tenancy()->initialize($this->testTenant);

        $user = TenantUser::query()->create([
            'name' => 'Microsoft User',
            'email' => 'microsoft.user@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');

        $sessionId = app(AuthSessionService::class)->start((string) $user->id, 'azure_sso');
        app(AuthSessionService::class)->markMfaVerified($sessionId);

        DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->update(['last_seen_at' => now()->subHour()]);

        $token = $user->createToken('access', ['*', 'session:'.$sessionId])->plainTextToken;

        tenancy()->end();

        $this->withHeaders(array_merge($this->tenantApiHeaders(), [
            'Authorization' => 'Bearer '.$token,
            'X-Session-Id' => $sessionId,
        ]))->getJson('/api/v1/me')->assertOk();

        tenancy()->initialize($this->testTenant);
        $lastSeen = DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->value('last_seen_at');
        tenancy()->end();

        $this->assertNotNull($lastSeen);
        $this->assertTrue(Carbon::parse((string) $lastSeen)->greaterThan(now()->subMinutes(2)));
    }

    public function test_microsoft_session_last_seen_updates_from_session_header_when_token_is_not_loaded(): void
    {
        tenancy()->initialize($this->testTenant);

        $user = TenantUser::query()->create([
            'name' => 'Microsoft Header User',
            'email' => 'microsoft.header@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');

        $sessionId = app(AuthSessionService::class)->start((string) $user->id, 'azure_sso');
        app(AuthSessionService::class)->markMfaVerified($sessionId);

        DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->update(['last_seen_at' => now()->subDay()]);

        tenancy()->end();

        $this->actingAs($user, 'sanctum')
            ->withHeaders(array_merge($this->tenantApiHeaders(), [
                'X-Session-Id' => $sessionId,
            ]))
            ->getJson('/api/v1/me')
            ->assertOk();

        tenancy()->initialize($this->testTenant);
        $lastSeen = DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->value('last_seen_at');
        tenancy()->end();

        $this->assertNotNull($lastSeen);
        $this->assertTrue(Carbon::parse((string) $lastSeen)->greaterThan(now()->subMinutes(2)));
    }

    public function test_admin_user_index_shows_last_active_for_microsoft_session(): void
    {
        tenancy()->initialize($this->testTenant);

        $user = TenantUser::query()->create([
            'name' => 'Ops Microsoft',
            'email' => 'ops.microsoft@test.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole('viewer');

        $sessionId = app(AuthSessionService::class)->start((string) $user->id, 'azure_sso');
        DB::connection('tenant')->table('auth_sessions')
            ->where('id', $sessionId)
            ->update(['last_seen_at' => now()->subMinutes(15)]);

        $adminSessionId = app(AuthSessionService::class)->start((string) $this->testTenantAdmin->id, 'local');
        app(AuthSessionService::class)->markMfaVerified($adminSessionId);
        $adminToken = $this->testTenantAdmin->createToken('access', ['*', 'session:'.$adminSessionId])->plainTextToken;

        tenancy()->end();

        $response = $this->withHeaders(array_merge($this->tenantApiHeaders(), [
            'Authorization' => 'Bearer '.$adminToken,
            'X-Session-Id' => $adminSessionId,
        ]))->getJson('/api/v1/admin/users?search=ops.microsoft@test.localhost');

        $response->assertOk()
            ->assertJsonPath('data.0.auth_methods.0', 'azure_sso');

        $this->assertNotNull($response->json('data.0.last_active_at'));
    }
}
