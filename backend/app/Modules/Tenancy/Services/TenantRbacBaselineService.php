<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Modules\AdminOne\Models\TenantPermission;
use App\Modules\AdminOne\Models\TenantRole;
use Spatie\Permission\PermissionRegistrar;

/**
 * Idempotent baseline permissions/roles for every tenant database.
 */
class TenantRbacBaselineService
{
    public function ensure(): void
    {
        $guard = 'sanctum';

        $permissions = [
            'dashboard:view',
            'gis:view',
            'sites:view',
            'user:manage',
            'tenant:manage',
            'role:manage',
            'project_one:view',
            'project_one:manage',
            'project_one:rollout:view',
            'project_one:rollout:manage',
            'project_one:rollout:gate:approve',
            'project_one:saq:manage',
            'project_one:cme:manage',
            'project_one:finance:view',
            'project_one:finance:edit',
            'project_one:finance:view_discipline',
            'project_one:playbook:configure',
            'tower_one:view',
            'fiber_one:view',
            'asset_one:view',
        ];

        foreach ($permissions as $name) {
            TenantPermission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => $guard],
            );
        }

        $viewer = TenantRole::query()->firstOrCreate(
            ['name' => 'viewer', 'guard_name' => $guard],
        );
        $viewer->syncPermissions([
            'dashboard:view',
            'gis:view',
            'sites:view',
            'project_one:view',
            'project_one:rollout:view',
            'tower_one:view',
            'fiber_one:view',
            'asset_one:view',
        ]);

        $admin = TenantRole::query()->firstOrCreate(
            ['name' => 'tenant_admin', 'guard_name' => $guard],
        );
        $admin->syncPermissions($permissions);

        $manager = TenantRole::query()->firstOrCreate(
            ['name' => 'manager', 'guard_name' => $guard],
        );
        $manager->syncPermissions([
            'dashboard:view',
            'gis:view',
            'sites:view',
            'user:manage',
            'project_one:view',
            'project_one:manage',
            'project_one:rollout:view',
            'project_one:rollout:manage',
            'project_one:rollout:gate:approve',
            'project_one:saq:manage',
            'project_one:cme:manage',
            'project_one:finance:view_discipline',
            'tower_one:view',
            'fiber_one:view',
            'asset_one:view',
        ]);

        $finance = TenantRole::query()->firstOrCreate(
            ['name' => 'finance', 'guard_name' => $guard],
        );
        $finance->syncPermissions([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:finance:view',
            'project_one:finance:edit',
        ]);

        /**
         * Operational roles aligned with rollout gate approval chains (saq, pmo, cme).
         * Assign on users + set matching rollout owner (saq_owner_id, pmo_owner_id, cme_pm_id).
         */
        $saqApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'saq_approver', 'guard_name' => $guard],
        );
        $saqApprover->syncPermissions([
            'dashboard:view',
            'gis:view',
            'sites:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:saq:manage',
        ]);

        $pmoApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'pmo_approver', 'guard_name' => $guard],
        );
        $pmoApprover->syncPermissions([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:rollout:manage',
        ]);

        $cmeApprover = TenantRole::query()->firstOrCreate(
            ['name' => 'cme_approver', 'guard_name' => $guard],
        );
        $cmeApprover->syncPermissions([
            'dashboard:view',
            'project_one:view',
            'project_one:rollout:view',
            'project_one:cme:manage',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
