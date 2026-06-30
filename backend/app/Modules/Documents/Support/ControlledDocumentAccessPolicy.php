<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

/**
 * Parsed from E-Approval form metadata_json.controlledDocumentSync.accessPolicy.
 *
 * Layered access (no code changes per tenant):
 * 1. Spatie permission documents:controlled:view (required for registry)
 * 2. optional viewerRoles — user must hold at least one when non-empty
 * 3. fullAccessRoles / fullAccessPermissions — bypass viewer role list and see all rows
 */
final class ControlledDocumentAccessPolicy
{
    /**
     * @param  list<string>  $viewerRoles
     * @param  list<string>  $fullAccessRoles
     * @param  list<string>  $fullAccessPermissions
     * @param  array<string, list<string>>  $roleDepartmentMap  role name => department codes; "*" = all
     */
    public function __construct(
        public readonly array $viewerRoles,
        public readonly array $fullAccessRoles,
        public readonly array $fullAccessPermissions,
        public readonly array $roleDepartmentMap,
    ) {}

    /**
     * @param  array<string, mixed>|null  $raw
     */
    public static function parse(?array $raw): self
    {
        if ($raw === null) {
            return self::defaults();
        }

        return new self(
            viewerRoles: self::normalizeStringList($raw['viewerRoles'] ?? $raw['viewer_roles'] ?? []),
            fullAccessRoles: self::normalizeStringList(
                $raw['fullAccessRoles'] ?? $raw['full_access_roles'] ?? ['document_controller', 'quality_manager'],
            ),
            fullAccessPermissions: self::normalizeStringList(
                $raw['fullAccessPermissions'] ?? $raw['full_access_permissions'] ?? ['documents:controlled:manage'],
            ),
            roleDepartmentMap: self::normalizeRoleDepartmentMap(
                $raw['roleDepartmentMap'] ?? $raw['role_department_map'] ?? [],
            ),
        );
    }

    public static function defaults(): self
    {
        return new self(
            viewerRoles: [],
            fullAccessRoles: ['document_controller', 'quality_manager'],
            fullAccessPermissions: ['documents:controlled:manage'],
            roleDepartmentMap: [],
        );
    }

    /**
     * @param  mixed  $value
     * @return array<string, list<string>>
     */
    private static function normalizeRoleDepartmentMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $role => $departments) {
            if (! is_string($role) || trim($role) === '' || ! is_array($departments)) {
                continue;
            }

            $codes = [];
            foreach ($departments as $department) {
                if (is_string($department) && trim($department) !== '') {
                    $codes[] = trim($department);
                }
            }

            if ($codes !== []) {
                $out[trim($role)] = array_values(array_unique($codes));
            }
        }

        return $out;
    }

    /**
     * @param  mixed  $value
     * @return list<string>
     */
    private static function normalizeStringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $out[] = trim($item);
            }
        }

        return array_values(array_unique($out));
    }
}
