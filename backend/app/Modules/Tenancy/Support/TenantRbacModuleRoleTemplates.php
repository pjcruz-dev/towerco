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
    public static function all(): array
    {
        return array_merge(
            self::coreRoles(),
            self::projectOneRoles(),
            self::ticketingRoles(),
            self::procurementRoles(),
            self::financeRoles(),
            self::eApprovalRoles(),
            self::documentsRoles(),
            self::controlledDocumentsRoles(),
            self::sitesRoles(),
            self::disciplineAddons(),
            self::legacyAliases(),
        );
    }

    /** @return array<string, list<string>> */
    private static function coreRoles(): array
    {
        return [
            // Global read-only landing — no module sidebar groups.
            'viewer' => ['dashboard:view'],
            'manager' => [
                'dashboard:view',
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
                'procurement_one:view',
                'procurement_one:documents:create',
                'procurement_one:documents:manage',
                'procurement_one:vendors:view',
                'procurement_one:vendors:manage',
                'procurement_one:inventory:view',
                'procurement_one:inventory:manage',
                'finance_one:view',
                'sites:view',
                'documents:view',
                'documents:upload',
                'documents:manage',
            ],
            'finance' => [
                'dashboard:view',
                'project_one:view',
                'project_one:rollout:view',
                'project_one:finance:view',
                'project_one:finance:edit',
                'finance_one:view',
                'finance_one:reports:view',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    private static function projectOneRoles(): array
    {
        return [
            'project_one_viewer' => [
                'dashboard:view',
                'project_one:view',
                'project_one:rollout:view',
            ],
            'project_one_contributor' => [
                'dashboard:view',
                'project_one:view',
                'project_one:rollout:view',
                'project_one:saq:manage',
                'project_one:cme:manage',
            ],
            'project_one_operator' => [
                'dashboard:view',
                'project_one:view',
                'project_one:manage',
                'project_one:rollout:view',
                'project_one:rollout:manage',
                'project_one:rollout:gate:approve',
                'project_one:saq:manage',
                'project_one:cme:manage',
                'project_one:finance:view_discipline',
            ],
            'project_one_admin' => [
                'dashboard:view',
                'project_one:view',
                'project_one:manage',
                'project_one:rollout:view',
                'project_one:rollout:manage',
                'project_one:rollout:gate:approve',
                'project_one:saq:manage',
                'project_one:cme:manage',
                'project_one:finance:view',
                'project_one:finance:edit',
                'project_one:playbook:configure',
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
    private static function procurementRoles(): array
    {
        return [
            'procurement_viewer' => [
                'dashboard:view',
                'procurement_one:view',
                'procurement_one:vendors:view',
                'procurement_one:inventory:view',
            ],
            'procurement_contributor' => [
                'dashboard:view',
                'procurement_one:view',
                'procurement_one:documents:create',
                'procurement_one:vendors:view',
                'procurement_one:inventory:view',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'procurement_operator' => [
                'dashboard:view',
                'procurement_one:view',
                'procurement_one:documents:create',
                'procurement_one:documents:manage',
                'procurement_one:vendors:view',
                'procurement_one:vendors:manage',
                'procurement_one:inventory:view',
                'procurement_one:inventory:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'procurement_admin' => [
                'dashboard:view',
                'procurement_one:view',
                'procurement_one:documents:create',
                'procurement_one:documents:manage',
                'procurement_one:vendors:view',
                'procurement_one:vendors:manage',
                'procurement_one:inventory:view',
                'procurement_one:inventory:manage',
                'procurement_one:settings:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    private static function financeRoles(): array
    {
        return [
            'finance_viewer' => [
                'dashboard:view',
                'finance_one:view',
            ],
            'finance_contributor' => [
                'dashboard:view',
                'finance_one:view',
                'finance_one:documents:create',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            'finance_operator' => [
                'dashboard:view',
                'finance_one:view',
                'finance_one:documents:create',
                'finance_one:documents:manage',
                'finance_one:budget:manage',
                'finance_one:contracts:manage',
                'finance_one:payments:manage',
                'finance_one:reports:view',
                'e_approval:view',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
            'finance_admin' => [
                'dashboard:view',
                'finance_one:view',
                'finance_one:documents:create',
                'finance_one:documents:manage',
                'finance_one:budget:manage',
                'finance_one:contracts:manage',
                'finance_one:payments:manage',
                'finance_one:reports:view',
                'finance_one:settings:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
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

    /**
     * Site & rollout document roles (binders, uploads, templates).
     *
     * @return array<string, list<string>>
     */
    private static function documentsRoles(): array
    {
        return [
            'documents_viewer' => [
                'dashboard:view',
                'documents:view',
            ],
            'documents_contributor' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
            ],
            'documents_operator' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
                'documents:manage',
            ],
            'documents_approver' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
                'documents:manage',
            ],
            'documents_admin' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
                'documents:manage',
                'documents:template:manage',
            ],
        ];
    }

    /**
     * Controlled Document Form (DCF) roles — managed through E-Approval workflow.
     *
     * @return array<string, list<string>>
     */
    private static function controlledDocumentsRoles(): array
    {
        return [
            // Read-only access to the controlled document register.
            'dcf_viewer' => [
                'dashboard:view',
                'documents:view',
                'documents:controlled:view',
            ],
            // Can submit new controlled documents and revisions via E-Approval.
            'dcf_author' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
                'documents:controlled:view',
                'documents:controlled:create',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
            ],
            // Can approve controlled-document E-Approval workflow steps.
            'dcf_approver' => [
                'dashboard:view',
                'documents:view',
                'documents:controlled:view',
                'documents:controlled:approve',
                'e_approval:view',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
            // Full document control: publish, obsolete, edit metadata, upload revisions.
            'dcf_controller' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
                'documents:manage',
                'documents:controlled:view',
                'documents:controlled:create',
                'documents:controlled:approve',
                'documents:controlled:manage',
                'e_approval:view',
                'e_approval:submissions:create',
                'e_approval:submissions:view',
                'e_approval:approve',
            ],
            // Admin: full DCF control including bulk import and E-Approval form management.
            'dcf_admin' => [
                'dashboard:view',
                'documents:view',
                'documents:upload',
                'documents:manage',
                'documents:template:manage',
                'documents:controlled:view',
                'documents:controlled:create',
                'documents:controlled:approve',
                'documents:controlled:manage',
                'documents:controlled:import',
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
    private static function sitesRoles(): array
    {
        return [
            'sites_viewer' => [
                'dashboard:view',
                'sites:view',
            ],
        ];
    }

    /** @return array<string, list<string>> */
    private static function disciplineAddons(): array
    {
        return [
            'saq_approver' => [
                'dashboard:view',
                'project_one:view',
                'project_one:rollout:view',
                'project_one:saq:manage',
            ],
            'pmo_approver' => [
                'dashboard:view',
                'project_one:view',
                'project_one:rollout:view',
                'project_one:rollout:manage',
            ],
            'cme_approver' => [
                'dashboard:view',
                'project_one:view',
                'project_one:rollout:view',
                'project_one:cme:manage',
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
