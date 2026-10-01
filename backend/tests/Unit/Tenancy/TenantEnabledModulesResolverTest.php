<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantEnabledModulesResolverTest extends TestCase
{
    public function test_platform_modules_always_include_the_product_set(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'e_approval', 'billings', 'ai_assistant']);

        $resolver = app(TenantEnabledModulesResolver::class);
        $platform = $resolver->platformModules();

        $this->assertSame(['core', 'team_access'], array_slice($platform, 0, 2));
        $this->assertContains('e_approval', $platform);
        $this->assertContains('ticketing', $platform);
        $this->assertContains('document_register', $platform);
        $this->assertContains('doc_extract', $platform);
        $this->assertContains('dynamic_entities', $platform);
        $this->assertContains('ai_assistant', $platform);
        $this->assertNotContains('documents', $platform);
        $this->assertNotContains('billings', $platform);
        $this->assertSame([
            'e_approval',
            'ticketing',
            'document_register',
            'doc_extract',
            'dynamic_entities',
            'ai_assistant',
        ], $resolver->toggleableModules());
    }

    public function test_tenant_override_limits_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'e_approval',
            'ticketing',
            'document_register',
            'doc_extract',
        ]);

        $tenant = new Tenant([
            'enabled_modules' => ['core', 'team_access', 'e_approval'],
        ]);

        $resolver = app(TenantEnabledModulesResolver::class);

        $this->assertSame(['core', 'team_access', 'e_approval'], $resolver->resolveForTenant($tenant));
    }

    public function test_null_tenant_override_uses_platform_default(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'e_approval']);

        $tenant = new Tenant([
            'enabled_modules' => null,
        ]);

        $resolver = app(TenantEnabledModulesResolver::class);
        $resolved = $resolver->resolveForTenant($tenant);

        $this->assertContains('document_register', $resolved);
        $this->assertContains('e_approval', $resolved);
        $this->assertNotContains('documents', $resolved);
        $this->assertNotContains('billings', $resolved);
    }

    public function test_document_register_is_in_the_platform_catalog(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'e_approval',
        ]);

        $resolver = app(TenantEnabledModulesResolver::class);
        $catalog = $resolver->catalogForPlatformApi();

        $this->assertContains('document_register', $resolver->toggleableModules());
        $this->assertContains('dynamic_entities', $resolver->toggleableModules());
        $this->assertSame('Document register', $catalog['labels']['document_register']);
        $this->assertSame('Dynamic Entities', $catalog['labels']['dynamic_entities']);
        $this->assertArrayHasKey('document_register', $catalog['descriptions']);
        $this->assertArrayHasKey('dynamic_entities', $catalog['descriptions']);
        $this->assertSame('AI Assistant', $catalog['labels']['ai_assistant']);
        $this->assertArrayHasKey('ai_assistant', $catalog['descriptions']);
        $this->assertArrayNotHasKey('documents', $catalog['labels']);
        $this->assertArrayNotHasKey('billings', $catalog['labels']);
    }
}
