<?php

declare(strict_types=1);

namespace Tests\Feature\AdminOne;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantUserIndexTest extends TestCase
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

    public function test_user_index_includes_security_summary_fields(): void
    {
        $target = $this->createTenantUser('ops.user@towerone.test', 'Ops User');
        $this->seedSession($target, 'azure_sso', now()->subDay());

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?search=ops.user@towerone.test');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $target->id)
            ->assertJsonPath('data.0.auth_methods.0', 'azure_sso')
            ->assertJsonPath('data.0.mfa_enrolled', false)
            ->assertJsonPath('data.0.mfa_required', false);

        $this->assertNotNull($response->json('data.0.last_active_at'));
    }

    public function test_user_index_filters_never_active_users(): void
    {
        $active = $this->createTenantUser('active.user@towerone.test', 'Active User');
        $inactive = $this->createTenantUser('never.user@towerone.test', 'Never User');
        $this->seedSession($active, 'local', now()->subHours(2));

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?last_active=never&per_page=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $inactive->id, $ids);
        $this->assertNotContains((string) $active->id, $ids);
    }

    public function test_user_index_filters_mfa_not_enrolled_users(): void
    {
        $plain = $this->createTenantUser('plain.user@towerone.test', 'Plain User');
        $enrolled = $this->createTenantUser('mfa.user@towerone.test', 'MFA User');
        $this->seedMfaFactor($enrolled);

        $response = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/admin/users?mfa=not_enrolled&per_page=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $plain->id, $ids);
        $this->assertNotContains((string) $enrolled->id, $ids);
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

    private function seedMfaFactor(TenantUser $user): void
    {
        tenancy()->initialize($this->testTenant);
        DB::table('mfa_factors')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'type' => 'totp',
            'secret_encrypted' => 'encrypted-secret',
            'is_primary' => true,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        tenancy()->end();
    }
}
