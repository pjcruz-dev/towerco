<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AzureGroupRoleMapper
{
    /**
     * @param  list<string>  $groupIds
     * @return list<string>
     */
    public function syncRolesForGroups(TenantUser $user, array $groupIds): array
    {
        $central = (string) config('tenancy.database.central_connection', 'central');
        $config = DB::connection($central)
            ->table('tenant_sso_configs')
            ->where('tenant_id', (string) tenant('id'))
            ->where('provider', 'azure')
            ->first();

        if (! $config || ! $config->group_mapping_rules) {
            return [];
        }

        $rules = json_decode((string) $config->group_mapping_rules, true);
        if (! is_array($rules) || $rules === []) {
            // Empty mapping {} — do not change Team & Access role assignments on sign-in.
            return $user->getRoleNames()->values()->all();
        }

        $roles = [];
        foreach ($groupIds as $groupId) {
            $mapped = $rules[$groupId] ?? [];
            if (is_array($mapped)) {
                foreach ($mapped as $role) {
                    if (is_string($role) && $role !== '') {
                        $roles[] = $role;
                    }
                }
            }
        }

        $roles = array_values(array_unique($roles));

        if ($roles === []) {
            // No Entra group matched a mapping rule — keep Team & Access assignments (e.g. e_approval_requestor).
            return $user->getRoleNames()->values()->all();
        }

        // Merge Entra-mapped roles with roles assigned in Team & Access (do not replace on every sign-in).
        $merged = array_values(array_unique(array_merge(
            $user->getRoleNames()->values()->all(),
            $roles,
        )));

        $user->syncRoles($merged);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $merged;
    }
}

