<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants\Concerns;

use App\Models\Tenant;

trait ResolvesTenantFromConsoleOptions
{
    protected function resolveTenantFromOptions(): ?Tenant
    {
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);

            return $tenant;
        }

        $domain = $this->option('domain');
        if (! is_string($domain) || $domain === '') {
            $domain = (string) config('toweros.demo.tenant_domain', 'alliance.localhost');
        }

        return Tenant::query()
            ->whereHas('domains', static fn ($query) => $query->where('domain', $domain))
            ->first();
    }
}
