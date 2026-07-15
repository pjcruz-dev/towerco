<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * System-provisioned tenant roles (non-deletable; permissions synced by baseline).
 */
final class TenantRbacSystemRoles
{
    /** Core cross-module tiers shown first in Team & Access. */
    public const CORE_BASELINE = ['tenant_admin', 'viewer', 'manager'];

    /** @var list<string> */
    public const ALL = [
        'tenant_admin',
        'viewer',
        'manager',
        'finance',
        // Project-One tiers
        'project_one_viewer',
        'project_one_contributor',
        'project_one_operator',
        'project_one_admin',
        // Ticketing tiers
        'ticketing_viewer',
        'ticketing_contributor',
        'ticketing_operator',
        'ticketing_admin',
        // Procurement tiers
        'procurement_viewer',
        'procurement_contributor',
        'procurement_operator',
        'procurement_admin',
        // Finance-One tiers
        'finance_viewer',
        'finance_contributor',
        'finance_operator',
        'finance_admin',
        // Documents tiers
        'documents_viewer',
        'documents_contributor',
        'documents_operator',
        'documents_admin',
        // Sites
        'sites_viewer',
        // E-Approval tiers
        'e_approval_viewer',
        'e_approval_requestor',
        'e_approval_approver',
        'e_approval_admin',
        // Project-One discipline add-ons
        'saq_approver',
        'pmo_approver',
        'cme_approver',
    ];

    public static function isSystem(string $roleName): bool
    {
        return in_array($roleName, self::ALL, true);
    }

    public static function isCoreBaseline(string $roleName): bool
    {
        return in_array($roleName, self::CORE_BASELINE, true);
    }

    /** @return list<string> */
    public static function moduleTierRoleNames(): array
    {
        return array_values(array_filter(
            self::ALL,
            static fn (string $name): bool => ! in_array($name, ['tenant_admin', 'viewer', 'manager', 'finance'], true)
                && ! in_array($name, ['saq_approver', 'pmo_approver', 'cme_approver'], true),
        ));
    }
}
