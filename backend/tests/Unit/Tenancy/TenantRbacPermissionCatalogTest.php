<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class TenantRbacPermissionCatalogTest extends TestCase
{
    public function test_default_enabled_modules_exclude_legacy_inventory_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'project_one', 'e_approval']);

        $catalog = app(TenantRbacPermissionCatalog::class);
        $enabled = $catalog->enabledPermissions();

        $this->assertContains('dashboard:view', $enabled);
        $this->assertContains('user:manage', $enabled);
        $this->assertContains('user:impersonate', $enabled);
        $this->assertContains('billing:view', $enabled);
        $this->assertContains('billing:manage', $enabled);
        $this->assertContains('project_one:view', $enabled);
        $this->assertContains('e_approval:view', $enabled);

        $this->assertNotContains('gis:view', $enabled);
        $this->assertNotContains('sites:view', $enabled);
        $this->assertNotContains('tower_one:view', $enabled);
        $this->assertNotContains('fiber_one:view', $enabled);
        $this->assertNotContains('asset_one:view', $enabled);
    }

    public function test_permission_groups_split_documents_and_document_register(): void
    {
        Config::set('toweros.tenant_modules.enabled', [
            'core',
            'team_access',
            'documents',
            'document_register',
        ]);

        $groups = app(TenantRbacPermissionCatalog::class)->permissionGroupsForApi();

        $this->assertArrayHasKey('documents', $groups);
        $this->assertArrayHasKey('document_register', $groups);
        $this->assertContains('documents:view', $groups['documents']['permissions']);
        $this->assertNotContains('documents:controlled:view', $groups['documents']['permissions']);
        $this->assertContains('documents:controlled:view', $groups['document_register']['permissions']);
    }

    public function test_document_register_permissions_require_module(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'team_access', 'documents']);

        $enabled = app(TenantRbacPermissionCatalog::class)->enabledPermissions();

        $this->assertContains('documents:view', $enabled);
        $this->assertNotContains('documents:controlled:view', $enabled);
    }

    public function test_permission_groups_only_include_enabled_modules(): void
    {
        Config::set('toweros.tenant_modules.enabled', ['core', 'project_one', 'e_approval']);

        $groups = app(TenantRbacPermissionCatalog::class)->permissionGroupsForApi();

        $this->assertArrayHasKey('core', $groups);
        $this->assertArrayHasKey('team_access', $groups);
        $this->assertArrayHasKey('project_one', $groups);
        $this->assertArrayHasKey('e_approval', $groups);
        $this->assertArrayNotHasKey('gis', $groups);
    }
}
