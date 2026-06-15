<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Identity\Services\TotpService;
use App\Modules\Platform\Services\PlatformMfaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

final class PlatformMfaAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $repository = app(ClientRepository::class);
        $provider = (string) config('auth.guards.api.provider', 'users');

        try {
            $repository->personalAccessClient($provider);
        } catch (RuntimeException) {
            $repository->createPersonalAccessGrantClient(
                config('app.name').' Personal Access Client',
                $provider,
            );
        }
    }

    #[Test]
    public function login_issues_token_when_platform_mfa_is_disabled(): void
    {
        config(['toweros.platform_mfa.required' => false]);

        $user = User::factory()->create([
            'email' => 'operator@toweros.local',
            'password' => bcrypt('password'),
            'is_platform_admin' => true,
            'platform_role' => 'superadmin',
        ]);

        $response = $this->postJson('/api/v1/platform/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.access_token', fn ($value) => is_string($value) && $value !== '')
            ->assertJsonPath('data.user.email', $user->email);
    }

    #[Test]
    public function login_requires_mfa_challenge_when_policy_is_enabled(): void
    {
        config(['toweros.platform_mfa.required' => true]);

        $user = User::factory()->create([
            'email' => 'secured@toweros.local',
            'password' => bcrypt('password'),
            'is_platform_admin' => true,
            'platform_role' => 'superadmin',
        ]);

        $secret = app(TotpService::class)->generateSecret();
        DB::table('platform_mfa_factors')->insert([
            'id' => (string) str()->uuid(),
            'user_id' => $user->id,
            'type' => 'totp',
            'secret_encrypted' => encrypt($secret),
            'is_primary' => true,
            'verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/platform/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.mfa_required', true)
            ->assertJsonPath('data.mfa_enrollment_required', false)
            ->assertJsonPath('data.mfa_challenge.id', fn ($value) => is_string($value) && $value !== '');
    }

    #[Test]
    public function protected_platform_route_rejects_token_without_mfa_verification_when_policy_enabled(): void
    {
        config(['toweros.platform_mfa.required' => true]);

        $user = User::factory()->create([
            'is_platform_admin' => true,
            'platform_role' => 'superadmin',
        ]);

        $tokenResult = $user->createToken('test');
        DB::table('oauth_access_tokens')
            ->where('id', $tokenResult->token->id)
            ->update(['mfa_verified_at' => null]);

        $this->withHeader('Authorization', 'Bearer '.$tokenResult->accessToken)
            ->getJson('/api/v1/platform/me')
            ->assertForbidden();
    }

    #[Test]
    public function unknown_platform_role_defaults_to_viewer_permissions(): void
    {
        $catalog = app(\App\Modules\Platform\Support\PlatformRoleCatalog::class);

        $this->assertSame('viewer', $catalog->normalizeRole('not-a-real-role'));
        $this->assertFalse($catalog->roleHasPermission('not-a-real-role', 'platform.tenants.manage'));
        $this->assertTrue($catalog->roleHasPermission('not-a-real-role', 'platform.console.view'));
    }
}
