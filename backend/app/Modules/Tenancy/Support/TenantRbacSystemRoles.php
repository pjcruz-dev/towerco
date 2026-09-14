<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * System-provisioned tenant roles (non-deletable; permissions synced by baseline).
 */
final class TenantRbacSystemRoles
{
    /** Core cross-module tiers shown first in Team & Access. */
    public const CORE_BASELINE = ['tenant_admin', 'billing', 'viewer', 'manager'];

    /** @var list<string> */
    public const ALL = [
        'tenant_admin',
        'billing',
        'viewer',
        'manager',
        // Ticketing tiers
        'ticketing_viewer',
        'ticketing_contributor',
        'ticketing_operator',
        'ticketing_admin',
        // E-Forms tiers
        'e_approval_viewer',
        'e_approval_requestor',
        'e_approval_approver',
        'e_approval_admin',
        // DocExtract tiers
        'doc_extract_viewer',
        'doc_extract_operator',
        'doc_extract_admin',
        // AI Assistant tiers
        'ai_assistant_user',
        'ai_assistant_admin',
        // Dynamic Entities tiers
        'dynamic_entities_viewer',
        'dynamic_entities_contributor',
        'dynamic_entities_admin',
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
            static fn (string $name): bool => ! in_array($name, ['tenant_admin', 'billing', 'viewer', 'manager'], true),
        ));
    }
}
