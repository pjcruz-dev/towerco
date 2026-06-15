<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use App\Modules\Tenancy\Support\TenantRbacPermissionCatalog;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class RoleCatalogService
{
    /** @var list<string> */
    public const BASELINE_ROLES = ['tenant_admin', 'viewer', 'manager'];

    public function __construct(
        private readonly TenantRbacBaselineService $rbacBaseline,
        private readonly TenantRbacPermissionCatalog $permissionCatalog,
    ) {}

    public function ensureBaseline(): void
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
        $this->ensureBaseline();

        $enabled = $this->permissionCatalog->enabledPermissions();

        $roles = TenantRole::query()
            ->where('guard_name', 'sanctum')
            ->with('permissions:id,name')
            ->orderBy('name')
            ->get()
            ->map(static function (TenantRole $role) use ($enabled): array {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'is_baseline' => in_array($role->name, self::BASELINE_ROLES, true),
                    'permissions' => $role->permissions
                        ->pluck('name')
                        ->filter(static fn (string $name): bool => in_array($name, $enabled, true))
                        ->values()
                        ->all(),
                ];
            })
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
     * @param  list<string>  $permissions
     */
    public function createCustomRole(string $name, array $permissions): TenantRole
    {
        $this->ensureBaseline();
        $normalized = $this->normalizeRoleName($name);

        if (in_array($normalized, self::BASELINE_ROLES, true)) {
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

    /**
     * @param  list<string>  $permissions
     */
    public function updateCustomRolePermissions(TenantRole $role, array $permissions): TenantRole
    {
        if (in_array($role->name, self::BASELINE_ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => [__('Baseline roles cannot be modified from the console.')],
            ]);
        }

        $this->assertPermissionsExist($permissions);
        $role->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role->fresh(['permissions']);
    }

    /**
     * @param  list<string>  $permissions
     */
    private function assertPermissionsExist(array $permissions): void
    {
        foreach ($permissions as $permission) {
            if (! $this->permissionCatalog->isEnabled($permission)) {
                throw ValidationException::withMessages([
                    'permissions' => [__('Permission :permission is not available for this tenant.', ['permission' => $permission])],
                ]);
            }

            if (! TenantPermission::query()->where('name', $permission)->where('guard_name', 'sanctum')->exists()) {
                throw ValidationException::withMessages([
                    'permissions' => [__('Permission :permission does not exist.', ['permission' => $permission])],
                ]);
            }
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
