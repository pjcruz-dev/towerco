<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\Tenant;
use App\Modules\Identity\Services\MfaService;
use App\Modules\Identity\Services\TenantPasskeysPolicyService;
use Illuminate\Validation\ValidationException;

final class TenantSecuritySettingsService
{
    public function __construct(
        private readonly MfaService $mfaService,
        private readonly TenantPasskeysPolicyService $passkeysPolicy,
    ) {}

    /**
     * @return array{
     *     mfa_required: bool,
     *     mfa_trust_days: int,
     *     mfa_global_enabled: bool,
     *     mfa_policy_active: bool,
     *     passkeys_enabled: bool,
     *     passkeys_global_enabled: bool,
     *     passkeys_default_enabled: bool,
     *     passkeys_policy: string,
     *     passkeys_satisfies_mfa: bool
     * }
     */
    public function show(): array
    {
        $tenant = $this->resolveTenant();

        return [
            'mfa_required' => (bool) ($tenant->mfa_required ?? false),
            'mfa_trust_days' => $this->normalizeTrustDays($tenant->mfa_trust_days ?? null),
            'mfa_global_enabled' => (bool) config('toweros.tenant_mfa.global_required', false),
            'mfa_policy_active' => $this->mfaService->isTenantMfaPolicyActive(),
            'passkeys_enabled' => $this->passkeysPolicy->isEnabled($tenant),
            'passkeys_global_enabled' => (bool) config('toweros.tenant_passkeys.enabled', true),
            'passkeys_default_enabled' => (bool) config('toweros.tenant_passkeys.default_enabled', false),
            'passkeys_policy' => $this->passkeysPolicy->policyMode($tenant),
            'passkeys_satisfies_mfa' => $this->passkeysPolicy->passkeySatisfiesMfa($tenant),
        ];
    }

    /**
     * @param  array{
     *     mfa_required: bool,
     *     mfa_trust_days?: int,
     *     passkeys_enabled?: bool,
     *     passkeys_policy?: string,
     *     passkeys_satisfies_mfa?: bool
     * }  $data
     * @return array{
     *     mfa_required: bool,
     *     mfa_trust_days: int,
     *     mfa_global_enabled: bool,
     *     mfa_policy_active: bool,
     *     passkeys_enabled: bool,
     *     passkeys_global_enabled: bool,
     *     passkeys_default_enabled: bool,
     *     passkeys_policy: string,
     *     passkeys_satisfies_mfa: bool
     * }
     */
    public function update(array $data): array
    {
        $tenant = $this->resolveTenant();
        $tenant->mfa_required = (bool) $data['mfa_required'];
        if (array_key_exists('mfa_trust_days', $data)) {
            $tenant->mfa_trust_days = $this->normalizeTrustDays($data['mfa_trust_days']);
        }
        if (array_key_exists('passkeys_enabled', $data)) {
            $tenant->passkeys_enabled = (bool) $data['passkeys_enabled'];
        }
        if (array_key_exists('passkeys_policy', $data)) {
            $policy = $this->passkeysPolicy->normalizePolicy($data['passkeys_policy']);
            $enabled = array_key_exists('passkeys_enabled', $data)
                ? (bool) $data['passkeys_enabled']
                : $this->passkeysPolicy->isEnabled($tenant);
            if ($policy === TenantPasskeysPolicyService::POLICY_REQUIRE && ! $enabled) {
                throw ValidationException::withMessages([
                    'passkeys_policy' => [__('Enable passkeys before requiring them for all users.')],
                ]);
            }
            $tenant->passkeys_policy = $policy;
        }
        if (array_key_exists('passkeys_satisfies_mfa', $data)) {
            $tenant->passkeys_satisfies_mfa = (bool) $data['passkeys_satisfies_mfa'];
        }
        $tenant->save();

        $this->mfaService->forgetTenantPolicyCache((string) $tenant->id);
        $this->mfaService->forgetTenantTrustDaysCache((string) $tenant->id);

        return $this->show();
    }

    private function normalizeTrustDays(mixed $days): int
    {
        if ($days === null || $days === '') {
            return (int) config('toweros.tenant_mfa.default_trust_days', 7);
        }

        return max(0, min(90, (int) $days));
    }

    private function resolveTenant(): Tenant
    {
        $tenantKey = tenant()?->getTenantKey();
        if ($tenantKey === null) {
            throw new \RuntimeException('Tenant context is required.');
        }

        /** @var Tenant|null $tenant */
        $tenant = Tenant::query()->find($tenantKey);
        if ($tenant === null) {
            throw new \RuntimeException('Tenant record not found.');
        }

        return $tenant;
    }
}
