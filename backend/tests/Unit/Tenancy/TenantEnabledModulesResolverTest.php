<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantEnabledModulesResolverTest extends TestCase
{
    public function test_platform_modules_follow_config(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'e_approval']);

        $resolver = app(TenantEnabledModulesResolver::class);

        $this->assertSame(['core', 'team_access', 'e_approval'], $resolver->platformModules());
        $this->assertSame(['e_approval'], $resolver->toggleableModules());
    }

    public function test_tenant_override_limits_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'project_one', 'e_approval']);

        $tenant = new Tenant([
            'enabled_modules' => ['core', 'team_access', 'e_approval'],
        ]);

        $resolver = app(TenantEnabledModulesResolver::class);

        $this->assertSame(['core', 'team_access', 'e_approval'], $resolver->resolveForTenant($tenant));
    }

    public function test_null_tenant_override_uses_platform_default(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'project_one', 'e_approval']);

        $tenant = new Tenant([
            'enabled_modules' => null,
        ]);

        $resolver = app(TenantEnabledModulesResolver::class);

        $this->assertSame(['core', 'team_access', 'project_one', 'e_approval'], $resolver->resolveForTenant($tenant));
    }
}
