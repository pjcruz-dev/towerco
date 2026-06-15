<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\Cache;

final class TenantAuthUserPayloadBuilder
{
    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array{id: string, name: string, email: string, source?: string}|null  $platformImpersonator
     */
    public function build(
        TenantUser $user,
        ?TenantUser $impersonator = null,
        ?array $platformImpersonator = null,
    ): array {
        $roles = $user->getRoleNames()->values()->all();
        $permissions = $user->getAllPermissions()->pluck('name')->values()->all();
        $tenantId = (string) tenant('id');
        $tenantDomain = $this->resolvePrimaryDomain();
        $tenantName = $this->resolveDisplayName($tenantDomain);

        $enabledModules = app(TenantEnabledModulesResolver::class)->resolveForCurrentTenant();
        $isImpersonating = $impersonator !== null || $platformImpersonator !== null;

        $payload = [
            'id' => $user->getKey(),
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $tenantId,
            'tenant_domain' => $tenantDomain,
            'roles' => $roles,
            'permissions' => $permissions,
            'enabled_modules' => $enabledModules,
            'is_impersonating' => $isImpersonating,
            'tenant_accesses' => [
                [
                    'tenant_id' => $tenantId,
                    'tenant_domain' => $tenantDomain,
                    'tenant_name' => $tenantName,
                    'roles' => $roles,
                    'permissions' => $permissions,
                    'enabled_modules' => $enabledModules,
                ],
            ],
        ];

        if ($platformImpersonator !== null) {
            $payload['impersonator'] = [
                'id' => $platformImpersonator['id'],
                'name' => $platformImpersonator['name'],
                'email' => $platformImpersonator['email'],
                'source' => $platformImpersonator['source'] ?? 'platform',
            ];
        } elseif ($impersonator !== null) {
            $payload['impersonator'] = [
                'id' => $impersonator->getKey(),
                'name' => $impersonator->name,
                'email' => $impersonator->email,
                'source' => 'tenant',
            ];
        }

        return $payload;
    }

    private function resolvePrimaryDomain(): ?string
    {
        $tenant = tenant();
        if (! $tenant instanceof Tenant) {
            return null;
        }

        $domain = Cache::remember(
            'toweros:tenant:primary_domain:'.(string) $tenant->id,
            3600,
            static fn (): ?string => $tenant->domains()->orderBy('id')->value('domain'),
        );

        return is_string($domain) && $domain !== '' ? strtolower($domain) : null;
    }

    private function resolveDisplayName(?string $domain): string
    {
        $tenant = tenant();
        if ($tenant instanceof Tenant) {
            $slug = trim((string) ($tenant->slug ?? ''));
            if ($slug !== '') {
                return strtoupper($slug);
            }
        }

        return $domain ?? (string) tenant('id');
    }
}
