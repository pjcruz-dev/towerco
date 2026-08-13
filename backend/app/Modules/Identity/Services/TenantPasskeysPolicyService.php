<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Identity\Models\WebAuthnCredential;

/**
 * Resolves passkey availability and Phase 4 policy (allow / prefer / require + MFA alignment).
 */
final class TenantPasskeysPolicyService
{
    public const POLICY_ALLOW = 'allow';

    public const POLICY_PREFER = 'prefer';

    public const POLICY_REQUIRE = 'require';

    /** @var list<string> */
    public const POLICIES = [self::POLICY_ALLOW, self::POLICY_PREFER, self::POLICY_REQUIRE];

    public function isEnabled(?Tenant $tenant = null): bool
    {
        if (! (bool) config('toweros.tenant_passkeys.enabled', true)) {
            return false;
        }

        $resolved = $tenant ?? $this->currentTenant();
        if ($resolved === null) {
            return false;
        }

        $explicit = $resolved->passkeys_enabled;
        if ($explicit === null || $explicit === '') {
            // Prefer explicit config; default true when key missing (Phase 2 BC / uncached installs).
            return (bool) config('toweros.tenant_passkeys.default_enabled', true);
        }

        return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @return self::POLICY_*
     */
    public function policyMode(?Tenant $tenant = null): string
    {
        if (! $this->isEnabled($tenant)) {
            return self::POLICY_ALLOW;
        }

        $resolved = $tenant ?? $this->currentTenant();
        $raw = is_string($resolved?->passkeys_policy ?? null)
            ? strtolower(trim((string) $resolved->passkeys_policy))
            : '';

        if (in_array($raw, self::POLICIES, true)) {
            return $raw;
        }

        $default = (string) config('toweros.tenant_passkeys.default_policy', self::POLICY_ALLOW);

        return in_array($default, self::POLICIES, true) ? $default : self::POLICY_ALLOW;
    }

    public function passkeySatisfiesMfa(?Tenant $tenant = null): bool
    {
        if (! $this->isEnabled($tenant)) {
            return false;
        }

        $resolved = $tenant ?? $this->currentTenant();
        $explicit = $resolved?->passkeys_satisfies_mfa ?? null;
        if ($explicit === null || $explicit === '') {
            return (bool) config('toweros.tenant_passkeys.default_satisfies_mfa', true);
        }

        return filter_var($explicit, FILTER_VALIDATE_BOOLEAN);
    }

    public function userHasPasskey(TenantUser $user): bool
    {
        return WebAuthnCredential::query()->where('user_id', $user->id)->exists();
    }

    /**
     * Org requires a passkey and this user has none (break-glass exempt).
     */
    public function isEnrollmentRequired(TenantUser $user, ?Tenant $tenant = null): bool
    {
        if (! $this->isEnabled($tenant) || $this->policyMode($tenant) !== self::POLICY_REQUIRE) {
            return false;
        }

        if ((bool) ($user->password_login_exempt ?? false)) {
            return false;
        }

        return ! $this->userHasPasskey($user);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     label: string,
     *     policy: string,
     *     satisfies_mfa: bool
     * }
     */
    public function publicStatus(?Tenant $tenant = null): array
    {
        $enabled = $this->isEnabled($tenant);

        return [
            'enabled' => $enabled,
            'label' => 'Sign in with passkey',
            'policy' => $enabled ? $this->policyMode($tenant) : self::POLICY_ALLOW,
            'satisfies_mfa' => $enabled && $this->passkeySatisfiesMfa($tenant),
        ];
    }

    /**
     * @return array{passkey_enrollment_required: bool, passkeys_policy: string}
     */
    public function loginFlags(TenantUser $user, ?Tenant $tenant = null): array
    {
        return [
            'passkey_enrollment_required' => $this->isEnrollmentRequired($user, $tenant),
            'passkeys_policy' => $this->policyMode($tenant),
        ];
    }

    public function assertEnabled(?Tenant $tenant = null): void
    {
        if ($this->isEnabled($tenant)) {
            return;
        }

        abort(403, __('Passkey sign-in is not enabled for this organization.'));
    }

    public function normalizePolicy(mixed $value): string
    {
        $raw = is_string($value) ? strtolower(trim($value)) : '';

        return in_array($raw, self::POLICIES, true) ? $raw : self::POLICY_ALLOW;
    }

    private function currentTenant(): ?Tenant
    {
        $tenant = tenant();

        return $tenant instanceof Tenant ? $tenant : null;
    }
}
