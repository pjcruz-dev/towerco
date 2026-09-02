<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * System-provisioned tenant roles (non-deletable; permissions synced by baseline).
 */
final class TenantRbacSystemRoles
{
    /** Canonical full-access tenant admin (Metacoresoft Administrator). */
    public const FULL_ADMIN = 'administrator';

    /** Core cross-module tiers shown first in Team & Access. */
    public const CORE_BASELINE = [
        'administrator',
        'billing',
        'viewer',
    ];

    /**
     * ATC Metacoresoft-parity operational roles (Alliance Towers ERP cutover).
     * Editable job roles are seeded once; only {@see self::ATC_LOCKED} stay system-locked.
     *
     * @var list<string>
     */
    public const ATC_OPERATIONAL = [
        'admin',
        'administrator',
        'commercial_sales_officer',
        'finance_officer',
        'procurement_officer',
        'project_manager',
        'sa_officer',
        'sales',
        'staff',
    ];

    /**
     * ATC roles that remain protected (full admin). Other ATC roles are editable like Metacoresoft.
     *
     * @var list<string>
     */
    public const ATC_LOCKED = [
        'administrator',
    ];

    /** @var list<string> */
    public const ALL = [
        'billing',
        'viewer',
        'finance',
        // Locked ATC administrator only — other ATC job roles are editable seeded roles.
        'administrator',
        // Ticketing tiers
        'ticketing_viewer',
        'ticketing_contributor',
        'ticketing_operator',
        'ticketing_admin',
        // E-Approval tiers
        'e_approval_viewer',
        'e_approval_requestor',
        'e_approval_approver',
        'e_approval_admin',
        // AI Assistant tiers
        'ai_assistant_user',
        'ai_assistant_admin',
    ];

    public static function isSystem(string $roleName): bool
    {
        return in_array($roleName, self::ALL, true);
    }

    public static function isCoreBaseline(string $roleName): bool
    {
        return in_array($roleName, self::CORE_BASELINE, true);
    }

    public static function isFullAdmin(string $roleName): bool
    {
        return $roleName === self::FULL_ADMIN;
    }

    public static function isAtcOperational(string $roleName): bool
    {
        return in_array($roleName, self::ATC_OPERATIONAL, true);
    }

    /** Editable ATC job roles (seeded once; not force-synced on every RBAC ensure). */
    public static function isAtcEditable(string $roleName): bool
    {
        return self::isAtcOperational($roleName) && ! in_array($roleName, self::ATC_LOCKED, true);
    }

    /** @return list<string> */
    public static function moduleTierRoleNames(): array
    {
        $exclude = array_merge(
            ['billing', 'viewer', 'finance', 'administrator'],
        );

        return array_values(array_filter(
            self::ALL,
            static fn (string $name): bool => ! in_array($name, $exclude, true),
        ));
    }
}
