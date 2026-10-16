<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
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
        if ($this->isWorkspaceOwner($user)) {
            return [];
        }

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
        $writes = ['create', 'edit', 'delete', 'import', 'smart_scan', 'undo', 'truncate', 'duplicate'];
        $moduleOk = match (true) {
            in_array($action, ['view', 'export'], true) => $user->can('dynamic_entities:view'),
            in_array($action, $writes, true) => $user->can('dynamic_entities:records:manage'),
            default => false,
        };
        if (! $moduleOk && $slug === 'workspace_users' && in_array($action, ['view', 'export'], true)) {
            $moduleOk = $user->can('ticketing:view')
                || $user->can('ticketing:tickets:create')
                || $user->can('ticketing:tickets:manage')
                || $user->can('ticketing:fields:manage');
        }
        if (! $moduleOk && in_array($slug, ['helpdesk_ticket', 'helpdesk_ticket_activities'], true)) {
            $moduleOk = match ($action) {
                'view', 'export' => $user->can('ticketing:view')
                    || $user->can('ticketing:tickets:create')
                    || $user->can('ticketing:tickets:manage'),
                'create', 'edit' => $user->can('ticketing:view')
                    || $user->can('ticketing:tickets:create')
                    || $user->can('ticketing:tickets:manage'),
                'delete' => $user->can('ticketing:tickets:manage'),
                default => false,
            };
        }
        if (! $moduleOk) {
            return false;
        }

        $allowed = RoleAccessMatrix::entityAllows($this->matrixFor($user), $slug, $action);

        return $allowed ?? true;
    }

    /**
     * Null means every status is allowed.
     *
     * @return list<string>|null
     */
    public function allowedStatuses(TenantUser $user, string $slug): ?array
    {
        return RoleAccessMatrix::allowedStatuses($this->matrixFor($user), $slug);
    }

    public function statusAllowed(TenantUser $user, string $slug, ?string $status): bool
    {
        $allowed = $this->allowedStatuses($user, $slug);
        if ($allowed === null) {
            return true;
        }
        $status = trim((string) $status);
        if ($status === '') {
            return true;
        }

        return in_array($status, $allowed, true);
    }

    public function fieldLevel(TenantUser $user, string $slug, string $field): string
    {
        return RoleAccessMatrix::fieldLevel($this->matrixFor($user), $slug, $field);
    }

    /**
     * Drop values the role cannot fill in. Full access and form-only stay.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function onlyEditable(TenantUser $user, string $slug, array $values): array
    {
        $matrix = $this->matrixFor($user);
        $out = [];
        foreach ($values as $field => $value) {
            if (! is_string($field)) {
                continue;
            }
            if (RoleAccessMatrix::editableOnForm(RoleAccessMatrix::fieldLevel($matrix, $slug, $field))) {
                $out[$field] = $value;
            }
        }

        return $out;
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

    /**
     * tenant_admin is the sole owner. Entity ACL on a second job role must not lock them out.
     * Controllers still authorize via Spatie permissions, not this role name.
     */
    private function isWorkspaceOwner(TenantUser $user): bool
    {
        return $user->hasRole(TenantRbacSystemRoles::FULL_ADMIN);
    }
}
