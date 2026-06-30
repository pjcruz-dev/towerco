<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RoleCatalogService
{
    /** @var list<string> */
    public const BASELINE_ROLES = TenantRbacSystemRoles::CORE_BASELINE;

    /** @var list<string> */
    public const SYSTEM_ROLES = TenantRbacSystemRoles::ALL;

    public function __construct(
        private readonly TenantRbacBaselineService $rbacBaseline,
        private readonly TenantRbacPermissionCatalog $permissionCatalog,
    ) {}

    public function ensureBaseline(): void
    {
        $this->rbacBaseline->ensure();
    }

    private function ensureCatalogReady(): void
    {
        $this->rbacBaseline->ensurePermissionsRegistered();
    }

    /**
     * @return array{
     *     roles: list<array<string, mixed>>,
     *     permissions: list<string>,
     *     permission_groups: array<string, array{label: string, permissions: list<string>}>,
     *     enabled_modules: list<string>
     * }
     */
    public function catalog(): array
    {
        $this->ensureCatalogReady();

        $enabled = $this->permissionCatalog->enabledPermissions();
        $userCounts = $this->userCountsByRoleId();

        $roles = TenantRole::query()
            ->where('guard_name', 'sanctum')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (TenantRole $role): array => $this->roleSummary($role, $enabled, $userCounts))
            ->values()
            ->all();

        return [
            'roles' => $roles,
            'permissions' => $enabled,
            'permission_groups' => $this->permissionCatalog->permissionGroupsForApi(),
            'enabled_modules' => $this->permissionCatalog->enabledModules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(TenantRole $role): array
    {
        $this->ensureCatalogReady();

        if ($role->guard_name !== 'sanctum') {
            throw ValidationException::withMessages([
                'role' => [__('Role not found.')],
            ]);
        }

        $role->loadMissing('permissions:id,name');
        $enabled = $this->permissionCatalog->enabledPermissions();
        $userCounts = $this->userCountsByRoleId();

        return [
            ...$this->roleSummary($role, $enabled, $userCounts),
            'users' => $this->assignedUsers($role),
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function createCustomRole(string $name, array $permissions): TenantRole
    {
        $this->ensureBaseline();
        $normalized = $this->normalizeRoleName($name);

        if (in_array($normalized, self::SYSTEM_ROLES, true)) {
            throw ValidationException::withMessages([
                'name' => [__('This role name is reserved.')],
            ]);
        }

        if (TenantRole::query()->where('name', $normalized)->where('guard_name', 'sanctum')->exists()) {
            throw ValidationException::withMessages([
                'name' => [__('A role with this name already exists.')],
            ]);
        }

        $this->assertPermissionsExist($permissions);

        $role = TenantRole::query()->create([
            'name' => $normalized,
            'guard_name' => 'sanctum',
        ]);
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh(['permissions']);
    }

    public function cloneRole(TenantRole $source, string $name): TenantRole
    {
        if ($source->guard_name !== 'sanctum') {
            throw ValidationException::withMessages([
                'role' => [__('Role not found.')],
            ]);
        }

        $source->loadMissing('permissions:id,name');
        $permissions = $source->permissions->pluck('name')->values()->all();

        if ($permissions === []) {
            throw ValidationException::withMessages([
                'role' => [__('Cannot clone a role without permissions.')],
            ]);
        }

        return $this->createCustomRole($name, $permissions);
    }

    /**
     * @param  list<string>  $permissions
     */
    public function updateCustomRolePermissions(TenantRole $role, array $permissions): TenantRole
    {
        if (TenantRbacSystemRoles::isSystem($role->name)) {
            throw ValidationException::withMessages([
                'role' => [__('System roles cannot be modified from the console.')],
            ]);
        }

        $this->assertPermissionsExist($permissions);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh(['permissions']);
    }

    public function deleteCustomRole(TenantRole $role): void
    {
        if (TenantRbacSystemRoles::isSystem($role->name)) {
            throw ValidationException::withMessages([
                'role' => [__('System roles cannot be deleted.')],
            ]);
        }

        $assignedCount = $this->assignedUserCount($role);
        if ($assignedCount > 0) {
            throw ValidationException::withMessages([
                'role' => [__('This role is assigned to :count user(s). Reassign them before deleting.', ['count' => $assignedCount])],
            ]);
        }

        $role->syncPermissions([]);
        $role->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array{
     *     left: array<string, mixed>,
     *     right: array<string, mixed>,
     *     only_left: list<string>,
     *     only_right: list<string>,
     *     shared: list<string>
     * }
     */
    public function compare(TenantRole $left, TenantRole $right): array
    {
        $this->ensureCatalogReady();

        foreach ([$left, $right] as $role) {
            if ($role->guard_name !== 'sanctum') {
                throw ValidationException::withMessages([
                    'role' => [__('Role not found.')],
                ]);
            }
        }

        $left->loadMissing('permissions:id,name');
        $right->loadMissing('permissions:id,name');
        $enabled = $this->permissionCatalog->enabledPermissions();
        $userCounts = $this->userCountsByRoleId();

        $leftPermissions = $left->permissions
            ->pluck('name')
            ->filter(static fn (string $name): bool => in_array($name, $enabled, true))
            ->sort()
            ->values()
            ->all();
        $rightPermissions = $right->permissions
            ->pluck('name')
            ->filter(static fn (string $name): bool => in_array($name, $enabled, true))
            ->sort()
            ->values()
            ->all();

        return [
            'left' => $this->roleSummary($left, $enabled, $userCounts),
            'right' => $this->roleSummary($right, $enabled, $userCounts),
            'only_left' => array_values(array_diff($leftPermissions, $rightPermissions)),
            'only_right' => array_values(array_diff($rightPermissions, $leftPermissions)),
            'shared' => array_values(array_intersect($leftPermissions, $rightPermissions)),
        ];
    }

    /**
     * @param  list<string>  $enabled
     * @param  array<int|string, int>  $userCounts
     * @return array<string, mixed>
     */
    private function roleSummary(TenantRole $role, array $enabled, array $userCounts): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'is_baseline' => TenantRbacSystemRoles::isCoreBaseline($role->name),
            'is_system' => TenantRbacSystemRoles::isSystem($role->name),
            'permissions' => $role->permissions
                ->pluck('name')
                ->filter(static fn (string $name): bool => in_array($name, $enabled, true))
                ->values()
                ->all(),
            'user_count' => $userCounts[(int) $role->id] ?? 0,
        ];
    }

    /**
     * @return array<int|string, int>
     */
    private function userCountsByRoleId(): array
    {
        $table = (string) config('permission.table_names.model_has_roles');

        return DB::table($table)
            ->where('model_type', TenantUser::class)
            ->select('role_id', DB::raw('COUNT(*) as user_count'))
            ->groupBy('role_id')
            ->pluck('user_count', 'role_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    private function assignedUserCount(TenantRole $role): int
    {
        $table = (string) config('permission.table_names.model_has_roles');

        return (int) DB::table($table)
            ->where('role_id', $role->id)
            ->where('model_type', TenantUser::class)
            ->count();
    }

    /**
     * @return list<array{id: string, name: string, email: string, is_active: bool}>
     */
    private function assignedUsers(TenantRole $role): array
    {
        return TenantUser::query()
            ->whereHas('roles', static function ($query) use ($role): void {
                $query->where('roles.id', $role->id);
            })
            ->orderBy('name')
            ->limit(100)
            ->get(['id', 'name', 'email', 'is_active'])
            ->map(static fn (TenantUser $user): array => [
                'id' => (string) $user->id,
                'name' => (string) $user->name,
                'email' => (string) $user->email,
                'is_active' => $user->isActive(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $permissions
     */
    private function assertPermissionsExist(array $permissions): void
    {
        if ($permissions === []) {
            return;
        }

        foreach ($permissions as $permission) {
            if (! $this->permissionCatalog->isEnabled($permission)) {
                throw ValidationException::withMessages([
                    'permissions' => [__('Permission :permission is not available for this tenant.', ['permission' => $permission])],
                ]);
            }
        }

        $existing = TenantPermission::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', $permissions)
            ->pluck('name')
            ->all();

        $missing = array_values(array_diff($permissions, $existing));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'permissions' => [__('Permission :permission does not exist.', ['permission' => $missing[0]])],
            ]);
        }
    }

    private function normalizeRoleName(string $name): string
    {
        $normalized = Str::of($name)->trim()->lower()->replace(' ', '_')->toString();

        if ($normalized === '' || strlen($normalized) > 64) {
            throw ValidationException::withMessages([
                'name' => [__('Role name is invalid.')],
            ]);
        }

        return $normalized;
    }
}
