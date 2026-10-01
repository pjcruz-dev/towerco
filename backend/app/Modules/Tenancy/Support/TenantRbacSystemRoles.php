<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Support;

/**
 * System-provisioned tenant roles (non-deletable; permissions synced by baseline).
 */
final class TenantRbacSystemRoles
{
    /** Sole workspace-owner role. Full enabled-permission set. Not entity-ACL scoped. */
    public const FULL_ADMIN = 'tenant_admin';

    /**
     * Historical full-access names. Not created. Hidden from Team & Access.
     * Users still holding these are folded onto {@see self::FULL_ADMIN} during RBAC ensure.
     *
     * @var list<string>
     */
    public const OWNER_ALIASES = ['administrator'];

    /** Core cross-module tiers shown first in Team & Access. */
    public const CORE_BASELINE = ['tenant_admin', 'billing', 'viewer', 'manager'];

    /** @var list<string> */
    public const ALL = [
        'tenant_admin',
        'billing',
        'viewer',
        'manager',
        'ticketing_viewer',
        'ticketing_contributor',
        'ticketing_operator',
        'ticketing_admin',
        'documents_viewer',
        'documents_contributor',
        'documents_operator',
        'documents_approver',
        'documents_admin',
        'dcf_viewer',
        'dcf_author',
        'dcf_approver',
        'dcf_controller',
        'dcf_admin',
        'e_approval_viewer',
        'e_approval_requestor',
        'e_approval_approver',
        'e_approval_admin',
        'doc_extract_viewer',
        'doc_extract_operator',
        'doc_extract_admin',
        'dynamic_entities_viewer',
        'dynamic_entities_contributor',
        'dynamic_entities_admin',
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

    public static function isOwnerAlias(string $roleName): bool
    {
        return in_array($roleName, self::OWNER_ALIASES, true);
    }

    public static function isHiddenFromCatalog(string $roleName): bool
    {
        return self::isOwnerAlias($roleName);
    }

    /**
     * Map deprecated owner aliases onto tenant_admin. Permission checks stay on Spatie names.
     *
     * @param  list<string>  $roles
     * @return list<string>
     */
    public static function canonicalizeAssignableRoles(array $roles): array
    {
        $canonical = [];
        foreach ($roles as $role) {
            $name = trim($role);
            if ($name === '') {
                continue;
            }
            if (self::isOwnerAlias($name)) {
                $name = self::FULL_ADMIN;
            }
            $canonical[] = $name;
        }

        return array_values(array_unique($canonical));
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
