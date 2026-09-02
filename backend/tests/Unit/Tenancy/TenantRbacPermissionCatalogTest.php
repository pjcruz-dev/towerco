<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantRbacPermissionCatalogTest extends TestCase
{
    public function test_default_enabled_modules_exclude_removed_infrastructure_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'dynamic_entities', 'e_approval']);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();

        $this->assertContains('dashboard:view', $enabled);
        $this->assertContains('workspace:environments:switch', $enabled);
        $this->assertContains('sidebar:manage', $enabled);
        $this->assertContains('notifications:manage', $enabled);
        $this->assertContains('printables:manage', $enabled);
        $this->assertContains('api_keys:manage', $enabled);
        $this->assertContains('system:manage', $enabled);
        $this->assertContains('html_reports:manage', $enabled);
        $this->assertContains('email_templates:manage', $enabled);
        $this->assertContains('automation:manage', $enabled);
        $this->assertContains('automation:manage', $enabled);
        $this->assertContains('user:manage', $enabled);
        $this->assertContains('user:impersonate', $enabled);
        $this->assertContains('billing:view', $enabled);
        $this->assertContains('billing:manage', $enabled);
        $this->assertContains('dynamic_entities:view', $enabled);
        $this->assertContains('e_approval:view', $enabled);

        $this->assertNotContains('gis:view', $enabled);
        $this->assertNotContains('tower_one:view', $enabled);
        $this->assertNotContains('fiber_one:view', $enabled);
        $this->assertNotContains('asset_one:view', $enabled);
    }

    public function test_billing_permissions_live_under_team_access(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
        ]);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();
        $groups = $catalog->permissionGroupsForApi();

        $this->assertContains('billing:view', $enabled);
        $this->assertContains('billing:manage', $enabled);
        $this->assertArrayNotHasKey('billings', $groups);
        $this->assertContains('billing:view', $groups['team_access']['permissions'] ?? []);
        $this->assertContains('billing:manage', $groups['team_access']['permissions'] ?? []);
    }

    public function test_permission_groups_only_include_enabled_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'dynamic_entities', 'e_approval']);

        $groups = app(TenantRbacPermissionCatalog::class)->permissionGroupsForApi();

        $this->assertArrayHasKey('core', $groups);
        $this->assertArrayHasKey('team_access', $groups);
        $this->assertArrayHasKey('dynamic_entities', $groups);
        $this->assertArrayHasKey('e_approval', $groups);
        $this->assertArrayNotHasKey('gis', $groups);
        $this->assertArrayNotHasKey('tower_one', $groups);
        $this->assertArrayNotHasKey('ai_assistant', $groups);
    }

    public function test_ai_assistant_permissions_require_module(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'ai_assistant',
        ]);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();
        $groups = $catalog->permissionGroupsForApi();

        $this->assertContains('ai_assistant:use', $enabled);
        $this->assertContains('ai_assistant:tools:use', $enabled);
        $this->assertContains('ai_assistant:actions:execute', $enabled);
        $this->assertContains('ai_assistant:knowledge:manage', $enabled);
        $this->assertContains('ai_assistant:prompts:manage', $enabled);
        $this->assertContains('ai_assistant:conversations:audit', $enabled);
        $this->assertArrayHasKey('ai_assistant', $groups);
        $this->assertSame('AI Assistant', $groups['ai_assistant']['label']);
    }
}
