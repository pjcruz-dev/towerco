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
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'e_approval', 'ticketing']);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();

        $this->assertContains('dashboard:view', $enabled);
        $this->assertContains('workspace:environments:switch', $enabled);
        $this->assertContains('user:manage', $enabled);
        $this->assertContains('user:impersonate', $enabled);
        $this->assertContains('organization:view', $enabled);
        $this->assertContains('organization:manage', $enabled);
        $this->assertContains('e_approval:view', $enabled);
        $this->assertContains('ticketing:view', $enabled);

        $this->assertNotContains('billing:view', $enabled);
        $this->assertNotContains('billing:manage', $enabled);
        $this->assertNotContains('gis:view', $enabled);
        $this->assertNotContains('sites:view', $enabled);
        $this->assertNotContains('project_one:view', $enabled);
        $this->assertNotContains('procurement_one:view', $enabled);
        $this->assertNotContains('finance_one:view', $enabled);
        $this->assertNotContains('tower_one:view', $enabled);
        $this->assertNotContains('fiber_one:view', $enabled);
        $this->assertNotContains('asset_one:view', $enabled);
    }

    public function test_billing_permissions_require_billings_module(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'billings',
        ]);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();
        $groups = $catalog->permissionGroupsForApi();

        $this->assertContains('billing:view', $enabled);
        $this->assertContains('billing:manage', $enabled);
        $this->assertArrayHasKey('billings', $groups);
        $this->assertSame('Billings', $groups['billings']['label']);
        $this->assertNotContains('billing:view', $groups['team_access']['permissions'] ?? []);
    }

        public function test_removed_modules_cannot_be_enabled(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'documents',
            'document_register',
            'sites',
            'gis',
        ]);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();
        $groups = $catalog->permissionGroupsForApi();

        $this->assertNotContains('documents:view', $enabled);
        $this->assertNotContains('documents:controlled:view', $enabled);
        $this->assertNotContains('sites:view', $enabled);
        $this->assertNotContains('gis:view', $enabled);
        $this->assertArrayNotHasKey('documents', $groups);
        $this->assertArrayNotHasKey('document_register', $groups);
        $this->assertArrayNotHasKey('sites', $groups);
        $this->assertArrayNotHasKey('gis', $groups);
    }

    public function test_permission_groups_only_include_enabled_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'e_approval', 'ticketing']);

        $groups = app(TenantRbacPermissionCatalog::class)->permissionGroupsForApi();

        $this->assertArrayHasKey('core', $groups);
        $this->assertArrayHasKey('team_access', $groups);
        $this->assertArrayHasKey('e_approval', $groups);
        $this->assertArrayHasKey('ticketing', $groups);
        $this->assertArrayNotHasKey('project_one', $groups);
        $this->assertArrayNotHasKey('gis', $groups);
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
        $this->assertContains('ai_assistant:conversations:audit', $enabled);
        $this->assertArrayHasKey('ai_assistant', $groups);
        $this->assertSame('AI Assistant', $groups['ai_assistant']['label']);
    }

    public function test_dynamic_entities_permissions_require_module(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'dynamic_entities',
        ]);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();
        $groups = $catalog->permissionGroupsForApi();

        $this->assertContains('dynamic_entities:view', $enabled);
        $this->assertContains('dynamic_entities:records:manage', $enabled);
        $this->assertContains('dynamic_entities:fields:manage', $enabled);
        $this->assertContains('dynamic_entities:entities:manage', $enabled);
        $this->assertContains('printables:manage', $enabled);
        $this->assertContains('html_reports:manage', $enabled);
        $this->assertContains('workflows:manage', $enabled);
        $this->assertContains('email_templates:manage', $enabled);
        $this->assertContains('automation:manage', $enabled);
        $this->assertContains('search_index:manage', $enabled);
        $this->assertContains('entity_hooks:manage', $enabled);
        $this->assertArrayHasKey('dynamic_entities', $groups);
        $this->assertSame('Dynamic Entities', $groups['dynamic_entities']['label']);
    }
}
