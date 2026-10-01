<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacModuleRoleTemplates;
use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
        $this->foldOwnerAliases($guard);
        $this->clearOwnerAccessMatrix($guard);
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

        $this->syncRole($guard, TenantRbacSystemRoles::FULL_ADMIN, $enabled);

        // Leftover full-access alias (not created). Keep perms in sync so fold is a no-op if empty.
        foreach (TenantRbacSystemRoles::OWNER_ALIASES as $alias) {
            $this->syncExistingRole($guard, $alias, $enabled);
        }
    }

    /**
     * Move leftover owner-alias assignments onto tenant_admin. Controllers keep checking permissions.
     */
    public function foldOwnerAliases(?string $guard = 'sanctum'): void
    {
        $guard ??= 'sanctum';

        try {
            if (
                ! Schema::connection('tenant')->hasTable('roles')
                || ! Schema::connection('tenant')->hasTable('model_has_roles')
            ) {
                return;
            }

            $owner = TenantRole::query()
                ->where('name', TenantRbacSystemRoles::FULL_ADMIN)
                ->where('guard_name', $guard)
                ->first();

            if ($owner === null) {
                return;
            }

            foreach (TenantRbacSystemRoles::OWNER_ALIASES as $aliasName) {
                $alias = TenantRole::query()
                    ->where('name', $aliasName)
                    ->where('guard_name', $guard)
                    ->first();

                if ($alias === null) {
                    continue;
                }

                $users = TenantUser::role($aliasName)->get();
                foreach ($users as $user) {
                    if (! $user->hasRole(TenantRbacSystemRoles::FULL_ADMIN)) {
                        $user->assignRole(TenantRbacSystemRoles::FULL_ADMIN);
                    }
                    $user->removeRole($aliasName);

                    Log::info('rbac.owner_alias_folded', [
                        'user_id' => (string) $user->id,
                        'from' => $aliasName,
                        'to' => TenantRbacSystemRoles::FULL_ADMIN,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('rbac.owner_alias_fold_failed', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function clearOwnerAccessMatrix(string $guard): void
    {
        if (! Schema::connection('tenant')->hasColumn('roles', 'access_matrix_json')) {
            return;
        }

        $names = [
            TenantRbacSystemRoles::FULL_ADMIN,
            ...TenantRbacSystemRoles::OWNER_ALIASES,
        ];

        TenantRole::query()
            ->where('guard_name', $guard)
            ->whereIn('name', $names)
            ->whereNotNull('access_matrix_json')
            ->update(['access_matrix_json' => null]);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function syncExistingRole(string $guard, string $name, array $permissions): void
    {
        $role = TenantRole::query()
            ->where('name', $name)
            ->where('guard_name', $guard)
            ->first();

        if ($role === null) {
            return;
        }

        $role->syncPermissions($permissions);
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
                if (
                    $role->name === TenantRbacSystemRoles::FULL_ADMIN
                    || TenantRbacSystemRoles::isOwnerAlias((string) $role->name)
                ) {
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
