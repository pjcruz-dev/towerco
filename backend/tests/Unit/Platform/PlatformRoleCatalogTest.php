<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Modules\Platform\Support\PlatformRoleCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlatformRoleCatalogTest extends TestCase
{
    private PlatformRoleCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new PlatformRoleCatalog;
    }

    #[Test]
    public function unknown_platform_role_defaults_to_viewer(): void
    {
        $this->assertSame(PlatformRoleCatalog::ROLE_VIEWER, $this->catalog->normalizeRole(null));
        $this->assertSame(PlatformRoleCatalog::ROLE_VIEWER, $this->catalog->normalizeRole(''));
        $this->assertSame(PlatformRoleCatalog::ROLE_VIEWER, $this->catalog->normalizeRole('invalid'));
    }

    #[Test]
    public function viewer_has_read_only_console_permissions(): void
    {
        $permissions = $this->catalog->permissionsForRole(PlatformRoleCatalog::ROLE_VIEWER);

        $this->assertContains(PlatformRoleCatalog::PERM_CONSOLE_VIEW, $permissions);
        $this->assertContains(PlatformRoleCatalog::PERM_TENANTS_VIEW, $permissions);
        $this->assertContains(PlatformRoleCatalog::PERM_AUDIT_VIEW, $permissions);
        $this->assertNotContains(PlatformRoleCatalog::PERM_TENANTS_MANAGE, $permissions);
        $this->assertNotContains(PlatformRoleCatalog::PERM_BILLING_VIEW, $permissions);
    }

    #[Test]
    public function manage_permission_implies_matching_view_permission(): void
    {
        $this->assertTrue(
            $this->catalog->roleHasPermission(
                PlatformRoleCatalog::ROLE_BILLING,
                PlatformRoleCatalog::PERM_BILLING_VIEW,
            ),
        );
        $this->assertTrue(
            $this->catalog->roleHasPermission(
                PlatformRoleCatalog::ROLE_SUPPORT,
                PlatformRoleCatalog::PERM_PLAYBOOKS_VIEW,
            ),
        );
    }

    #[Test]
    public function superadmin_has_all_permissions(): void
    {
        foreach ($this->catalog->allPermissions() as $permission) {
            $this->assertTrue(
                $this->catalog->roleHasPermission(PlatformRoleCatalog::ROLE_SUPERADMIN, $permission),
            );
        }
    }
}
