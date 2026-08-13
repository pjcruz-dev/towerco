<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\WebAuthnCredential;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\TenantPasskeysPolicyService;
use Illuminate\Support\Str;
use Tests\Support\Concerns\InteractsWithInMemoryTenantApi;
use Tests\TestCase;

final class TenantPasskeysPhase4PolicyTest extends TestCase
{
    use InteractsWithInMemoryTenantApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootInMemoryTenantApi();

        config([
            'toweros.tenant_passkeys.enabled' => true,
            'toweros.tenant_passkeys.default_enabled' => true,
            'toweros.tenant_passkeys.default_satisfies_mfa' => true,
            'toweros.tenant_mfa.global_required' => true,
        ]);

        $this->testTenant->mfa_required = true;
        $this->testTenant->passkeys_enabled = true;
        $this->testTenant->passkeys_satisfies_mfa = true;
        $this->testTenant->passkeys_policy = 'allow';
        $this->testTenant->save();
    }

    public function test_passkey_login_skips_totp_when_satisfies_mfa(): void
    {
        tenancy()->initialize($this->testTenant);

        $user = $this->testTenantAdmin;
        $mfa = app(MfaService::class);
        $state = $mfa->resolveLoginMfaState($user, (string) Str::uuid(), 'webauthn');

        $this->assertFalse($state['mfa_required']);
        $this->assertTrue($state['mark_verified']);

        tenancy()->end();
    }

    public function test_password_login_still_requires_totp_when_mfa_policy_active(): void
    {
        tenancy()->initialize($this->testTenant);

        $user = $this->testTenantAdmin;
        $sessionId = (string) Str::uuid();
        \Illuminate\Support\Facades\DB::table('auth_sessions')->insert([
            'id' => $sessionId,
            'user_id' => $user->id,
            'auth_method' => 'local',
            'state' => 'active',
            'last_seen_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $mfa = app(MfaService::class);
        $state = $mfa->resolveLoginMfaState($user, $sessionId, 'local');

        $this->assertTrue($state['mfa_required']);
        $this->assertFalse($state['mark_verified']);

        tenancy()->end();
    }

    public function test_require_policy_marks_enrollment_until_passkey_exists(): void
    {
        $this->testTenant->passkeys_policy = 'require';
        $this->testTenant->save();

        tenancy()->initialize($this->testTenant);
        $policy = app(TenantPasskeysPolicyService::class);
        $user = TenantUser::query()->findOrFail($this->testTenantAdmin->id);

        $this->assertTrue($policy->isEnrollmentRequired($user));

        WebAuthnCredential::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => (string) $user->id,
            'credential_id' => 'phase4-cred',
            'public_key' => "-----BEGIN PUBLIC KEY-----\nMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE\n-----END PUBLIC KEY-----",
            'sign_count' => 0,
            'label' => 'Laptop',
        ]);

        $this->assertFalse($policy->isEnrollmentRequired($user->fresh()));
        tenancy()->end();
    }
}
