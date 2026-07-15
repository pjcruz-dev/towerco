<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * Canonical tenant permission catalog grouped by module.
 *
 * Only permissions from {@see enabledModules()} are provisioned on new tenants and
 * exposed in the Team & Access role editor.
 */
final class TenantRbacPermissionCatalog
{
    public function __construct(
        private readonly TenantEnabledModulesResolver $enabledModulesResolver,
    ) {}

    /** @var array<string, list<string>> */
    private const MODULE_PERMISSIONS = [
        'core' => [
            'dashboard:view',
            'workspace:audit:view',
        ],
        'team_access' => [
            'user:manage',
            'user:impersonate',
            'role:manage',
            'tenant:manage',
            'billing:view',
            'billing:manage',
        ],
        'project_one' => [
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
        ],
        'e_approval' => [
            'e_approval:view',
            'e_approval:forms:manage',
            'e_approval:submissions:create',
            'e_approval:submissions:view',
            'e_approval:approve',
            'e_approval:audit:view',
            'e_approval:settings:manage',
        ],
        'ticketing' => [
            'ticketing:view',
            'ticketing:tickets:create',
            'ticketing:tickets:manage',
            'ticketing:settings:manage',
        ],
        'procurement_one' => [
            'procurement_one:view',
            'procurement_one:documents:create',
            'procurement_one:documents:manage',
            'procurement_one:settings:manage',
            'procurement_one:vendors:view',
            'procurement_one:vendors:manage',
            'procurement_one:inventory:view',
            'procurement_one:inventory:manage',
        ],
        'finance_one' => [
            'finance_one:view',
            'finance_one:documents:create',
            'finance_one:documents:manage',
            'finance_one:budget:manage',
            'finance_one:contracts:manage',
            'finance_one:payments:manage',
            'finance_one:reports:view',
            'finance_one:settings:manage',
        ],
        // Disabled modules (retained for reference; not in default enabled set).
        'gis' => ['gis:view'],
        'sites' => ['sites:view'],
        'tower_one' => ['tower_one:view'],
        'fiber_one' => ['fiber_one:view'],
        'asset_one' => ['asset_one:view', 'asset_one:assets:manage'],
        'documents' => [
            'documents:view',
            'documents:upload',
            'documents:manage',
            'documents:template:manage',
        ],
        'document_register' => [
            'documents:controlled:view',
            'documents:controlled:create',
            'documents:controlled:approve',
            'documents:controlled:manage',
            'documents:controlled:import',
        ],
    ];

    /** @var array<string, string> */
    private const MODULE_LABELS = [
        'core' => 'Dashboard',
        'team_access' => 'Team & Access',
        'project_one' => 'Project-One',
        'e_approval' => 'E-Approval',
        'ticketing' => 'Ticketing',
        'procurement_one' => 'Procurement-One',
        'finance_one' => 'Finance-One',
        'gis' => 'GIS',
        'sites' => 'Sites',
        'tower_one' => 'Tower-One',
        'fiber_one' => 'Fiber-One',
        'asset_one' => 'Asset-One',
        'documents' => 'Documents',
        'document_register' => 'Document register',
    ];

    /**
     * @return list<string>
     */
    public function enabledModules(): array
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            return $this->enabledModulesResolver->resolveForCurrentTenant();
        }

        return $this->enabledModulesResolver->platformModules();
    }

    /**
     * @return list<string>
     */
    public function enabledPermissions(): array
    {
        $permissions = [];
        foreach ($this->enabledModules() as $module) {
            foreach ($this->permissionsForModule($module) as $permission) {
                $permissions[] = $permission;
            }
        }

        return array_values(array_unique($permissions));
    }

    public function isEnabled(string $permission): bool
    {
        return in_array($permission, $this->enabledPermissions(), true);
    }

    /**
     * @return list<string>
     */
    public function permissionsForModule(string $module): array
    {
        return self::MODULE_PERMISSIONS[$module] ?? [];
    }

    /**
     * @return array<string, array{label: string, permissions: list<string>}>
     */
    public function permissionGroupsForApi(): array
    {
        $groups = [];
        foreach ($this->enabledModules() as $module) {
            $permissions = $this->permissionsForModule($module);
            if ($permissions === []) {
                continue;
            }

            $groups[$module] = [
                'label' => self::MODULE_LABELS[$module] ?? $module,
                'permissions' => $permissions,
            ];
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public function allKnownPermissions(): array
    {
        $all = [];
        foreach (self::MODULE_PERMISSIONS as $permissions) {
            foreach ($permissions as $permission) {
                $all[] = $permission;
            }
        }

        return array_values(array_unique($all));
    }
}
