<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Tenancy\Support\TenantRbacModuleRoleTemplates;
use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
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
        $this->migrateLegacyTenantAdminToAdministrator($guard);
        $this->syncSystemRoles($guard, $enabled);
        $this->pruneDisabledPermissionsFromAllRoles($enabled, $guard);
        $this->pruneObsoleteRoles($guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Collapse legacy TowerOS `tenant_admin` into Metacoresoft `administrator`.
     */
    private function migrateLegacyTenantAdminToAdministrator(string $guard): void
    {
        $legacy = TenantRole::query()
            ->where('guard_name', $guard)
            ->where('name', 'tenant_admin')
            ->first();

        if ($legacy === null) {
            return;
        }

        $admin = TenantRole::query()->firstOrCreate(
            ['name' => TenantRbacSystemRoles::FULL_ADMIN, 'guard_name' => $guard],
        );

        $modelHasRoles = (string) config('permission.table_names.model_has_roles');
        $rolePivotKey = 'role_id';
        $modelKey = (string) config('permission.column_names.model_morph_key', 'model_id');
        if ($modelKey === '' || $modelKey === 'model_id') {
            // TowerOS tenants use UUID morph keys.
            if (\Illuminate\Support\Facades\Schema::connection('tenant')->hasColumn($modelHasRoles, 'model_uuid')) {
                $modelKey = 'model_uuid';
            } else {
                $modelKey = 'model_id';
            }
        }

        $rows = \Illuminate\Support\Facades\DB::connection('tenant')
            ->table($modelHasRoles)
            ->where($rolePivotKey, $legacy->getKey())
            ->get();

        foreach ($rows as $row) {
            $modelId = $row->{$modelKey};
            $alreadyHasAdmin = \Illuminate\Support\Facades\DB::connection('tenant')
                ->table($modelHasRoles)
                ->where($rolePivotKey, $admin->getKey())
                ->where('model_type', $row->model_type)
                ->where($modelKey, $modelId)
                ->exists();

            if (! $alreadyHasAdmin) {
                \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table($modelHasRoles)
                    ->insert([
                        $rolePivotKey => $admin->getKey(),
                        'model_type' => $row->model_type,
                        $modelKey => $modelId,
                    ]);
            }
        }

        \Illuminate\Support\Facades\DB::connection('tenant')
            ->table($modelHasRoles)
            ->where($rolePivotKey, $legacy->getKey())
            ->delete();
    }

    /**
     * Remove legacy module tier roles no longer in the catalog (e.g. dyn_*).
     */
    private function pruneObsoleteRoles(string $guard): void
    {
        $obsolete = [
            'tenant_admin',
            'manager',
            'dyn_viewer',
            'dyn_operator',
            'dyn_admin',
            'project_one_viewer',
            'project_one_contributor',
            'project_one_operator',
            'project_one_admin',
            'procurement_viewer',
            'procurement_contributor',
            'procurement_operator',
            'procurement_admin',
            'finance_viewer',
            'finance_contributor',
            'finance_operator',
            'finance_admin',
            'documents_viewer',
            'documents_contributor',
            'documents_operator',
            'documents_approver',
            'documents_admin',
            'sites_viewer',
            'dcf_viewer',
            'dcf_author',
            'dcf_approver',
            'dcf_controller',
            'dcf_admin',
            'saq_approver',
            'pmo_approver',
            'cme_approver',
        ];

        $kept = array_merge(
            array_keys(TenantRbacModuleRoleTemplates::all()),
            [TenantRbacSystemRoles::FULL_ADMIN],
            TenantRbacSystemRoles::ATC_OPERATIONAL,
        );

        TenantRole::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $obsolete)
            ->whereNotIn('name', $kept)
            ->get()
            ->each(function (TenantRole $role): void {
                $role->permissions()->detach();
                // Avoid Spatie Role::delete() users() morph (guard model can be unset in tenant context).
                \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table(config('permission.table_names.model_has_roles'))
                    ->where('role_id', $role->getKey())
                    ->delete();
                \Illuminate\Support\Facades\DB::connection('tenant')
                    ->table(config('permission.table_names.roles'))
                    ->where($role->getKeyName(), $role->getKey())
                    ->delete();
            });
    }

    /**
     * @param  list<string>  $enabled
     */
    private function syncSystemRoles(string $guard, array $enabled): void
    {
        $templates = TenantRbacModuleRoleTemplates::all();

        foreach ($templates as $roleName => $permissions) {
            $filtered = $this->filterEnabled($permissions, $enabled);
            // Metacoresoft job roles: create with defaults, then allow console edits to stick.
            if (TenantRbacSystemRoles::isAtcEditable($roleName)) {
                $this->ensureRoleSeeded($guard, $roleName, $filtered);

                continue;
            }
            $this->syncRole($guard, $roleName, $filtered);
        }

        // Full-access tenant administrator (Metacoresoft Administrator).
        $this->syncRole($guard, TenantRbacSystemRoles::FULL_ADMIN, $enabled);
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
     * Create role + permissions only when missing — do not overwrite edited ATC job roles.
     *
     * @param  list<string>  $permissions
     */
    private function ensureRoleSeeded(string $guard, string $name, array $permissions): void
    {
        $role = TenantRole::query()->firstOrCreate(
            ['name' => $name, 'guard_name' => $guard],
        );

        if ($role->wasRecentlyCreated || $role->permissions()->count() === 0) {
            $role->syncPermissions($permissions);
        }
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
                if (TenantRbacSystemRoles::isFullAdmin($role->name)) {
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
