<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Schema;

/**
 * Resolves Metacoresoft-style entity / field ACL from the user's tenant roles.
 */
final class DynRoleAccessService
{
    /**
     * @return array<string, mixed>
     */
    public function matrixFor(TenantUser $user): array
    {
        if (! Schema::connection('tenant')->hasColumn('roles', 'access_matrix_json')) {
            return [];
        }

        $matrices = [];
        foreach ($user->roles as $role) {
            if (! $role instanceof TenantRole) {
                continue;
            }
            $matrices[] = $role->accessMatrix();
        }

        return RoleAccessMatrix::merge($matrices);
    }

    public function canEntity(TenantUser $user, string $slug, string $action): bool
    {
        $moduleOk = match ($action) {
            'view', 'export' => $user->can('dynamic_entities:view'),
            'create', 'edit', 'delete' => $user->can('dynamic_entities:records:manage'),
            default => false,
        };
        if (! $moduleOk) {
            return false;
        }

        $allowed = RoleAccessMatrix::entityAllows($this->matrixFor($user), $slug, $action);

        return $allowed ?? true;
    }

    public function fieldLevel(TenantUser $user, string $slug, string $field): string
    {
        return RoleAccessMatrix::fieldLevel($this->matrixFor($user), $slug, $field);
    }

    public function canWorkflow(TenantUser $user, string $slug, string $action): bool
    {
        if (! $user->can('dynamic_entities:records:manage')) {
            return false;
        }
        $matrix = $this->matrixFor($user);
        $workflows = $matrix['workflows'] ?? null;
        if (! is_array($workflows) || $workflows === []) {
            return true;
        }

        return (bool) ($workflows[$slug][$action] ?? false);
    }

    /**
     * @return list<array{logic: 'and'|'or', rules: list<array{field: string, operator: string, value?: string}>}>
     */
    public function dataFilterGroups(TenantUser $user, string $slug): array
    {
        return RoleAccessMatrix::filterGroupsFor($this->matrixFor($user), $slug);
    }

    public function viewOwnOnly(TenantUser $user, string $slug): bool
    {
        return RoleAccessMatrix::viewOwnOnly($this->matrixFor($user), $slug);
    }
}
