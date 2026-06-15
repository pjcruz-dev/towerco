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
        $this->assertContains('project_one:view', $enabled);
        $this->assertContains('e_approval:view', $enabled);

        $this->assertNotContains('gis:view', $enabled);
        $this->assertNotContains('sites:view', $enabled);
        $this->assertNotContains('tower_one:view', $enabled);
        $this->assertNotContains('fiber_one:view', $enabled);
        $this->assertNotContains('asset_one:view', $enabled);
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
