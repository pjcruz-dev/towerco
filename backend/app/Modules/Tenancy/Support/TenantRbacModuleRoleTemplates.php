<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * Per-module role tiers. Each role only grants one module (+ dashboard) so users
 * do not see unrelated sidebar groups unless they hold multiple roles.
 *
 * Tiers: viewer → contributor (create / resubmit) → operator (+ manage / approve) → admin (+ settings)
 *
 * @return array<string, list<string>>
 */
final class TenantRbacModuleRoleTemplates
{
    /**
     * Roles that must NOT receive the in-app AI Assistant (SaaS billing-only access).
     *
     * @var list<string>
     */
    private const ASSISTANT_EXCLUDED_ROLES = ['billing'];

    public static function all(): array
    {
        $roles = array_merge(
            self::coreRoles(),
            self::atcOperationalRoles(),
            self::ticketingRoles(),
            self::eApprovalRoles(),
            self::aiAssistantRoles(),
            self::legacyAliases(),
        );

        return self::withAssistantBaseline($roles);
    }

    /**
     * Grant `ai_assistant:use` to every functional role so any workspace user gets in-app help.
     * The RBAC baseline service filters this against enabled permissions, so it only takes
     * effect when the ai_assistant module is enabled for the tenant.
     *
     * @param  array<string, list<string>>  $roles
     * @return array<string, list<string>>
     */
    private static function withAssistantBaseline(array $roles): array
    {
        foreach ($roles as $roleName => $permissions) {
            if (in_array($roleName, self::ASSISTANT_EXCLUDED_ROLES, true)) {
                continue;
            }
            if (! in_array('ai_assistant:use', $permissions, true)) {
                $permissions[] = 'ai_assistant:use';
                $roles[$roleName] = array_values($permissions);
            }
        }

        return $roles;
    }

    /** @return array<string, list<string>> */
    private static function coreRoles(): array
    {
        return [
            // Global read-only landing — no module sidebar groups.
            'viewer' => [
                'dashboard:view',
                'ai_assistant:use',
            ],
            // SaaS billing only — not tenant administration (users/roles/settings).
            'billing' => [
                'dashboard:view',
                'billing:view',
                'billing:manage',
            ],
            'finance' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
            ],
        ];
    }

    /**
     * Metacoresoft Role Management parity — Alliance Towers operational job roles.
     *
     * @return array<string, list<string>>
     */
    private static function atcOperationalRoles(): array
    {
        return [
            'admin' => [
                'dashboard:view',
                'workspace:audit:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'dynamic_entities:fields:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
                'ticketing:view',
                'ticketing:tickets:create',
                'ticketing:tickets:manage',
            ],
            'administrator' => [
                'dashboard:view',
            ],
            'commercial_sales_officer' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'finance_officer' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
            'procurement_officer' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
            'project_manager' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
                'ticketing:view',
                'ticketing:tickets:create',
                'ticketing:tickets:manage',
            ],
            'sa_officer' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'sales' => [
                'dashboard:view',
                'dynamic_entities:view',
                'dynamic_entities:records:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'staff' => [
                'dashboard:view',
                'dynamic_entities:view',
                'ticketing:view',
                'ticketing:tickets:create',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    private static function ticketingRoles(): array
    {
        return [
            'ticketing_viewer' => [
                'dashboard:view',
                'ticketing:view',
            ],
            'ticketing_contributor' => [
                'dashboard:view',
                'ticketing:view',
                'ticketing:tickets:create',
            ],
            'ticketing_operator' => [
                'dashboard:view',
                'ticketing:view',
                'ticketing:tickets:create',
                'ticketing:tickets:manage',
            ],
            'ticketing_admin' => [
                'dashboard:view',
                'ticketing:view',
                'ticketing:tickets:create',
                'ticketing:tickets:manage',
                'ticketing:settings:manage',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    private static function eApprovalRoles(): array
    {
        return [
            'e_approval_viewer' => [
                'dashboard:view',
                'e_approval:view',
                'e_approval:submissions:view',
            ],
            'e_approval_requestor' => [
                'dashboard:view',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'e_approval_approver' => [
                'dashboard:view',
                'e_approval:view',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
            'e_approval_admin' => [
                'dashboard:view',
                'e_approval:view',
                'e_approval:forms:manage',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
                'e_approval:audit:view',
                'e_approval:settings:manage',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    private static function aiAssistantRoles(): array
    {
        return [
            'ai_assistant_user' => [
                'dashboard:view',
                'ai_assistant:use',
                'ai_assistant:tools:use',
                'ai_assistant:actions:execute',
            ],
            'ai_assistant_admin' => [
                'dashboard:view',
                'ai_assistant:use',
                'ai_assistant:tools:use',
                'ai_assistant:actions:execute',
                'ai_assistant:knowledge:manage',
                'ai_assistant:prompts:manage',
                'ai_assistant:conversations:audit',
            ],
        ];
    }

    /**
     * Roles kept for backward compatibility (same permissions as tier equivalents).
     *
     * @return array<string, list<string>>
     */
    private static function legacyAliases(): array
    {
        return [];
    }
}
