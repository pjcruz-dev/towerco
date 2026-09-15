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
            'workspace:environments:switch',
            // AdminOne / workspace platform tools (always available with core)
            'sidebar:manage',
            'system:manage',
            'api_keys:manage',
            'notifications:manage',
        ],
        'team_access' => [
            'user:manage',
            'user:impersonate',
            'role:manage',
            'tenant:manage',
            'organization:view',
            'organization:manage',
        ],
        'billings' => [
            'billing:view',
            'billing:manage',
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
        'dynamic_entities' => [
            'dynamic_entities:view',
            'dynamic_entities:records:manage',
            'dynamic_entities:fields:manage',
            'dynamic_entities:entities:manage',
            // Dyn feature surfaces (controllers gate on these names)
            'printables:manage',
            'html_reports:manage',
            'workflows:manage',
            'email_templates:manage',
            'automation:manage',
            'search_index:manage',
            'entity_hooks:manage',
        ],
        'ai_assistant' => [
            'ai_assistant:use',
            'ai_assistant:tools:use',
            'ai_assistant:actions:execute',
            'ai_assistant:knowledge:manage',
            'ai_assistant:prompts:manage',
            'ai_assistant:conversations:audit',
        ],
        'doc_extract' => [
            'doc-extract:view',
            'doc-extract:run',
            'doc-extract:templates:manage',
            'doc-extract:export',
        ],
    ];

    /** @var array<string, string> */
    private const MODULE_LABELS = [
        'core' => 'Dashboard',
        'team_access' => 'Team & Access',
        'e_approval' => 'E-Forms',
        'ticketing' => 'Ticketing',
        'dynamic_entities' => 'Dynamic Entities',
        'billings' => 'Billings',
        'ai_assistant' => 'AI Assistant',
        'doc_extract' => 'DocExtract',
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
