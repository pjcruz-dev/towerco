<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Tenancy\Support\TenantRbacModuleRoleTemplates;
use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent baseline permissions/roles for every tenant database.
 */
class TenantRbacBaselineService
{
    public function __construct(
        private readonly TenantRbacPermissionCatalog $catalog,
    ) {}

    public function ensurePermissionsRegistered(): void
    {
        $guard = 'sanctum';

        foreach ($this->catalog->enabledPermissions() as $name) {
            TenantPermission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
            );
        }
    }

    public function ensure(): void
    {
        $guard = 'sanctum';
        $enabled = $this->catalog->enabledPermissions();

        $this->ensurePermissionsRegistered();
        $this->syncSystemRoles($guard, $enabled);
        $this->pruneDisabledPermissionsFromAllRoles($enabled, $guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $enabled
     */
    private function syncSystemRoles(string $guard, array $enabled): void
    {
        $templates = TenantRbacModuleRoleTemplates::all();

        foreach ($templates as $roleName => $permissions) {
            $this->syncRole($guard, $roleName, $this->filterEnabled($permissions, $enabled));
        }

        $this->syncRole($guard, 'tenant_admin', $enabled);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncRole(string $guard, string $name, array $permissions): void
    {
        $role = TenantRole::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => $guard],
        );
        $role->syncPermissions($permissions);
    }

    /**
     * @param  list<string>  $enabled
     */
    private function pruneDisabledPermissionsFromAllRoles(array $enabled, string $guard): void
    {
        TenantRole::query()
            ->where('guard_name', $guard)
            ->with('permissions:id,name')
            ->get()
            ->each(function (TenantRole $role) use ($enabled): void {
                if ($role->name === 'tenant_admin') {
                    return;
                }

                $current = $role->permissions->pluck('name')->all();
                $filtered = $this->filterEnabled($current, $enabled);

                if (count($filtered) !== count($current)) {
                    $role->syncPermissions($filtered);
                }
            });
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $enabled
     * @return list<string>
     */
    private function filterEnabled(array $permissions, array $enabled): array
    {
        return array_values(array_intersect($permissions, $enabled));
    }
}
