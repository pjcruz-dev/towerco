<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Tests\TestCase;

final class TenantRbacSystemRolesTest extends TestCase
{
    public function test_canonicalize_maps_administrator_onto_tenant_admin(): void
    {
        $this->assertSame(
            ['tenant_admin', 'viewer'],
            TenantRbacSystemRoles::canonicalizeAssignableRoles(['administrator', 'viewer', 'administrator']),
        );
    }

    public function test_administrator_is_hidden_from_catalog(): void
    {
        $this->assertTrue(TenantRbacSystemRoles::isHiddenFromCatalog('administrator'));
        $this->assertFalse(TenantRbacSystemRoles::isHiddenFromCatalog('tenant_admin'));
        $this->assertFalse(TenantRbacSystemRoles::isHiddenFromCatalog('admin'));
        $this->assertFalse(TenantRbacSystemRoles::isHiddenFromCatalog('dynamic_entities_admin'));
    }

    public function test_dynamic_entities_admin_is_a_system_role(): void
    {
        $this->assertTrue(TenantRbacSystemRoles::isSystem('dynamic_entities_admin'));
        $this->assertSame('tenant_admin', TenantRbacSystemRoles::FULL_ADMIN);
    }
}
