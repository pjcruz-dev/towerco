<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
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

    /**
     * Lightweight: register permission names only (for role catalog API). Does not re-sync all roles.
     */
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

        $this->syncBaselineRoles($guard, $enabled);
        $this->pruneDisabledPermissionsFromAllRoles($enabled, $guard);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $enabled
     */
    private function syncBaselineRoles(string $guard, array $enabled): void
    {
        $viewer = TenantRole::query()->firstOrCreate(
            ['name' => 'viewer', 'guard_name' => $guard],
        );
        $viewer->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'e_approval:view',
            'e_approval:submissions:view',
            'ticketing:view',
            'ticketing:tickets:create',
        ], $enabled));

        $admin = TenantRole::query()->firstOrCreate(
            ['name' => 'tenant_admin', 'guard_name' => $guard],
        );
        $admin->syncPermissions($enabled);

        $manager = TenantRole::query()->firstOrCreate(
            ['name' => 'manager', 'guard_name' => $guard],
        );
        $manager->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'user:manage',
            'project_one:view',
            'project_one:manage',
            'project_one:rollout:view',
            'project_one:rollout:manage',
            'project_one:rollout:gate:approve',
            'project_one:saq:manage',
            'project_one:cme:manage',
            'project_one:finance:view_discipline',
            'e_approval:view',
            'e_approval:submissions:create',
            'e_approval:submissions:view',
            'e_approval:approve',
            'ticketing:view',
            'ticketing:tickets:create',
            'ticketing:tickets:manage',
        ], $enabled));

        $finance = TenantRole::query()->firstOrCreate(
            ['name' => 'finance', 'guard_name' => $guard],
        );
        $finance->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:finance:view',
            'project_one:finance:edit',
        ], $enabled));

        $saqApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'saq_approver', 'guard_name' => $guard],
        );
        $saqApprover->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:saq:manage',
        ], $enabled));

        $pmoApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'pmo_approver', 'guard_name' => $guard],
        );
        $pmoApprover->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:rollout:manage',
        ], $enabled));

        $cmeApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'cme_approver', 'guard_name' => $guard],
        );
        $cmeApprover->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:cme:manage',
        ], $enabled));

        $eApprovalAdmin = TenantRole::query()->firstOrCreate(
            ['name' => 'e_approval_admin', 'guard_name' => $guard],
        );
        $eApprovalAdmin->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'e_approval:view',
            'e_approval:forms:manage',
            'e_approval:submissions:create',
            'e_approval:submissions:view',
            'e_approval:approve',
            'e_approval:audit:view',
            'e_approval:settings:manage',
        ], $enabled));

        $eApprovalApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'e_approval_approver', 'guard_name' => $guard],
        );
        $eApprovalApprover->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'e_approval:view',
            'e_approval:submissions:view',
            'e_approval:approve',
        ], $enabled));

        $eApprovalRequestor = TenantRole::query()->firstOrCreate(
            ['name' => 'e_approval_requestor', 'guard_name' => $guard],
        );
        $eApprovalRequestor->syncPermissions($this->filterEnabled([
            'dashboard:view',
            'e_approval:view',
            'e_approval:submissions:create',
            'e_approval:submissions:view',
        ], $enabled));
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
