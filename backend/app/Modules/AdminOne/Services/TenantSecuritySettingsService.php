<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Models\Tenant;
use App\Modules\Identity\Services\MfaService;

final class TenantSecuritySettingsService
{
    public function __construct(
        private readonly MfaService $mfaService,
    ) {}

    /**
     * @return array{
     *     mfa_required: bool,
     *     mfa_global_enabled: bool,
     *     mfa_policy_active: bool
     * }
     */
    public function show(): array
    {
        $tenant = $this->resolveTenant();

        return [
            'mfa_required' => (bool) ($tenant->mfa_required ?? false),
            'mfa_global_enabled' => (bool) config('toweros.tenant_mfa.global_required', false),
            'mfa_policy_active' => $this->mfaService->isTenantMfaPolicyActive(),
        ];
    }

    /**
     * @param  array{mfa_required: bool}  $data
     * @return array{
     *     mfa_required: bool,
     *     mfa_global_enabled: bool,
     *     mfa_policy_active: bool
     * }
     */
    public function update(array $data): array
    {
        $tenant = $this->resolveTenant();
        $tenant->mfa_required = (bool) $data['mfa_required'];
        $tenant->save();

        $this->mfaService->forgetTenantPolicyCache((string) $tenant->id);

        return $this->show();
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
