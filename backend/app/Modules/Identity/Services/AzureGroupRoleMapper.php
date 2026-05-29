<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;

class AzureGroupRoleMapper
{
    /**
     * @param  list<string>  $groupIds
     * @return list<string>
     */
    public function syncRolesForGroups(TenantUser $user, array $groupIds): array
    {
        $config = DB::table('tenant_sso_configs')
            ->where('tenant_id', (string) tenant('id'))
            ->where('provider', 'azure')
            ->first();

        if (! $config || ! $config->group_mapping_rules) {
            return [];
        }

        $rules = json_decode((string) $config->group_mapping_rules, true);
        if (! is_array($rules)) {
            return [];
        }

        $roles = [];
        foreach ($groupIds as $groupId) {
            $mapped = $rules[$groupId] ?? [];
            if (is_array($mapped)) {
                foreach ($mapped as $role) {
                    if (is_string($role)) {
                        $roles[] = $role;
                    }
                }
            }
        }

        $roles = array_values(array_unique($roles));
        if ($roles === []) {
            $roles = [env('TENANT_SSO_DEFAULT_ROLE', 'viewer')];
        }
        $user->syncRoles($roles);

        return $roles;
    }
}

