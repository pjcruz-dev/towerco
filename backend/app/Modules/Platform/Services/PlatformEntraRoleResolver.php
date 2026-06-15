<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Support\PlatformRoleCatalog;

final class PlatformEntraRoleResolver
{
    public function __construct(
        private readonly PlatformRoleCatalog $roles,
    ) {}

    /**
     * @param  list<string>  $groupIds
     */
    public function resolveRoleFromGroups(array $groupIds): ?string
    {
        /** @var array<string, string> $map */
        $map = config('toweros.platform_auth.entra_group_role_map', []);
        if ($map === []) {
            return null;
        }

        $matchedRoles = [];
        foreach ($groupIds as $groupId) {
            $normalized = strtolower(trim($groupId));
            if ($normalized === '' || ! isset($map[$normalized])) {
                continue;
            }

            $role = $this->roles->normalizeRole($map[$normalized]);
            $matchedRoles[$role] = true;
        }

        if ($matchedRoles === []) {
            return null;
        }

        if (isset($matchedRoles[PlatformRoleCatalog::ROLE_SUPERADMIN])) {
            return PlatformRoleCatalog::ROLE_SUPERADMIN;
        }
        if (isset($matchedRoles[PlatformRoleCatalog::ROLE_BILLING])) {
            return PlatformRoleCatalog::ROLE_BILLING;
        }
        if (isset($matchedRoles[PlatformRoleCatalog::ROLE_SUPPORT])) {
            return PlatformRoleCatalog::ROLE_SUPPORT;
        }

        return PlatformRoleCatalog::ROLE_VIEWER;
    }

    /**
     * @return list<string>
     */
    public function extractGroupIds(mixed $raw): array
    {
        if (is_string($raw)) {
            return [strtolower(trim($raw))];
        }

        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (is_string($item)) {
                $ids[] = strtolower(trim($item));
                continue;
            }
            if (is_array($item) && isset($item['id']) && is_string($item['id'])) {
                $ids[] = strtolower(trim($item['id']));
            }
        }

        return array_values(array_filter(array_unique($ids)));
    }
}
