<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Core\Http\Middleware\EnsureActiveSession;
use App\Core\Http\Middleware\EnsureMfaVerified;
use App\Modules\Identity\Models\WebAuthnCredential;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantWebAuthnApiTest extends TestCase
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

        config([
            'toweros.tenant_passkeys.enabled' => true,
            'toweros.tenant_passkeys.default_enabled' => true,
        ]);
    }

    public function test_authenticated_user_can_request_registration_options(): void
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/auth/webauthn/register/options', [
                'label' => 'Work laptop',
            ]);

        $res->assertOk();
        $res->assertJsonPath('data.rp_id', 'test.localhost');
        $res->assertJsonStructure([
            'data' => [
                'challenge_id',
                'publicKey' => [
                    'rp',
                    'user',
                    'challenge',
                    'pubKeyCredParams',
                ],
                'rp_id',
            ],
        ]);
        $this->assertNotEmpty($res->json('data.challenge_id'));
    }

    public function test_authenticated_user_can_request_registration_options_via_get(): void
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/auth/webauthn/register/options?label=Laptop');

        $res->assertOk();
        $res->assertJsonPath('data.rp_id', 'test.localhost');
        $this->assertNotEmpty($res->json('data.challenge_id'));
    }

    public function test_list_credentials_starts_empty(): void
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/auth/webauthn/credentials');

        $res->assertOk();
        $res->assertJsonPath('data.credentials', []);
        $res->assertJsonPath('data.rp_id', 'test.localhost');
    }

    public function test_login_options_without_email_returns_discoverable_challenge(): void
    {
        $res = $this->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/auth/webauthn/login/options', []);

        $res->assertOk();
        $res->assertJsonPath('data.rp_id', 'test.localhost');
        $this->assertNotEmpty($res->json('data.challenge_id'));
        $this->assertIsArray($res->json('data.publicKey'));
    }

    public function test_login_options_with_email_requires_existing_passkey(): void
    {
        $res = $this->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/auth/webauthn/login/options', [
                'email' => 'admin@test.localhost',
            ]);

        $res->assertUnprocessable();
    }

    public function test_user_can_revoke_own_passkey(): void
    {
        tenancy()->initialize($this->testTenant);
        $id = (string) Str::uuid();
        WebAuthnCredential::query()->create([
            'id' => $id,
            'user_id' => (string) $this->testTenantAdmin->id,
            'credential_id' => 'abc123credential',
            'public_key' => "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE\n-----END PUBLIC KEY-----",
            'sign_count' => 0,
            'label' => 'Temp',
        ]);
        tenancy()->end();

        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->deleteJson("/api/v1/auth/webauthn/credentials/{$id}");

        $res->assertOk();
        $res->assertJsonPath('data.revoked', true);

        tenancy()->initialize($this->testTenant);
        $this->assertNull(WebAuthnCredential::query()->find($id));
        tenancy()->end();
    }

    public function test_register_verify_rejects_expired_challenge(): void
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/auth/webauthn/register/verify', [
                'challenge_id' => (string) Str::uuid(),
                'credential' => [
                    'id' => 'x',
                    'rawId' => 'x',
                    'type' => 'public-key',
                    'response' => [
                        'clientDataJSON' => 'e30=',
                        'attestationObject' => 'e30=',
                    ],
                ],
            ]);

        $res->assertUnprocessable();
    }

    public function test_login_options_forbidden_when_passkeys_disabled(): void
    {
        $this->testTenant->passkeys_enabled = false;
        $this->testTenant->save();

        $res = $this->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/auth/webauthn/login/options', []);

        $res->assertForbidden();
    }

    public function test_register_options_forbidden_when_passkeys_disabled(): void
    {
        $this->testTenant->passkeys_enabled = false;
        $this->testTenant->save();

        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->postJson('/api/v1/auth/webauthn/register/options', []);

        $res->assertForbidden();
    }

    public function test_credentials_index_reports_enabled_flag(): void
    {
        $res = $this->actingAsTenantAdmin()
            ->withHeaders($this->tenantApiHeaders())
            ->getJson('/api/v1/auth/webauthn/credentials');

        $res->assertOk();
        $res->assertJsonPath('data.enabled', true);
    }
}
