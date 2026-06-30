<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Support\ControlledDocumentAccessPolicy;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;

final class ControlledDocumentAccessService
{
    public function __construct(
        private readonly ControlledDocumentFormResolverService $forms,
    ) {}

    public function policy(): ControlledDocumentAccessPolicy
    {
        return $this->forms->resolveAccessPolicy();
    }

    public function canAccessRegistry(TenantUser $user): bool
    {
        if (! $user->can('documents:controlled:view')) {
            return false;
        }

        return $this->passesRoleGate($user, $this->policy());
    }

    public function canViewDocument(TenantUser $user, ControlledDocument $document): bool
    {
        if (! $this->canAccessRegistry($user)) {
            return false;
        }

        if ($this->hasFullRegistryAccess($user, $this->policy())) {
            return true;
        }

        $allowedDepartments = $this->resolveAllowedDepartments($user, $this->policy());
        if ($allowedDepartments === null) {
            return true;
        }

        if ($allowedDepartments === []) {
            return false;
        }

        $department = trim((string) ($document->department ?? ''));

        return $department !== '' && in_array($department, $allowedDepartments, true);
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     */
    public function applyRegistryScope(Builder $query, TenantUser $user): void
    {
        if ($this->hasFullRegistryAccess($user, $this->policy())) {
            return;
        }

        $allowedDepartments = $this->resolveAllowedDepartments($user, $this->policy());
        if ($allowedDepartments === null) {
            return;
        }

        if ($allowedDepartments === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('department', $allowedDepartments);
    }

  /**
     * @return list<string>|null null = no department filter
     */
    private function resolveAllowedDepartments(TenantUser $user, ControlledDocumentAccessPolicy $policy): ?array
    {
        if ($policy->roleDepartmentMap === []) {
            return null;
        }

        $allowed = [];
        foreach ($user->getRoleNames() as $roleName) {
            $mapped = $policy->roleDepartmentMap[(string) $roleName] ?? [];
            if (in_array('*', $mapped, true)) {
                return null;
            }
            $allowed = array_merge($allowed, $mapped);
        }

        return array_values(array_unique($allowed));
    }

    private function passesRoleGate(TenantUser $user, ControlledDocumentAccessPolicy $policy): bool
    {
        if ($this->hasFullRegistryAccess($user, $policy)) {
            return true;
        }

        if ($policy->viewerRoles === []) {
            return true;
        }

        return $user->hasAnyRole($policy->viewerRoles);
    }

    private function hasFullRegistryAccess(TenantUser $user, ControlledDocumentAccessPolicy $policy): bool
    {
        foreach ($policy->fullAccessPermissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        if ($policy->fullAccessRoles !== [] && $user->hasAnyRole($policy->fullAccessRoles)) {
            return true;
        }

        return false;
    }
}
