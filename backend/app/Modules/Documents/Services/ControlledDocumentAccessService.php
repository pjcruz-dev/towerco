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

        $policy = $this->policy();

        if ($this->hasFullRegistryAccess($user, $policy)) {
            return true;
        }

        if ($this->shouldScopeToOwnDocuments($user, $policy)) {
            return $this->userOwnsOrRevisedDocument($user, $document);
        }

        $allowedDepartments = $this->resolveAllowedDepartments($user, $policy);
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
        $policy = $this->policy();

        if ($this->hasFullRegistryAccess($user, $policy)) {
            return;
        }

        if ($this->shouldScopeToOwnDocuments($user, $policy)) {
            $this->applyOwnDocumentsScope($query, $user);

            return;
        }

        $allowedDepartments = $this->resolveAllowedDepartments($user, $policy);
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
     * Authors (default: dcf_author) see only documents they created or revised,
     * unless they also hold a broader register role (viewer/approver/controller/admin).
     */
    private function shouldScopeToOwnDocuments(TenantUser $user, ControlledDocumentAccessPolicy $policy): bool
    {
        if ($policy->ownOnlyRoles === []) {
            return false;
        }

        $userRoles = $user->getRoleNames()->map(static fn ($role): string => (string) $role)->all();
        $hasOwnOnlyRole = count(array_intersect($userRoles, $policy->ownOnlyRoles)) > 0;
        if (! $hasOwnOnlyRole) {
            return false;
        }

        $broaderRoles = array_values(array_unique(array_merge(
            $policy->viewerRoles,
            $policy->fullAccessRoles,
            array_keys($policy->roleDepartmentMap),
            ['dcf_viewer', 'dcf_approver', 'dcf_controller', 'dcf_admin'],
        )));
        $broaderRoles = array_values(array_diff($broaderRoles, $policy->ownOnlyRoles));

        return count(array_intersect($userRoles, $broaderRoles)) === 0;
    }

    /**
     * @param  Builder<ControlledDocument>  $query
     */
    private function applyOwnDocumentsScope(Builder $query, TenantUser $user): void
    {
        $userId = (string) $user->id;

        $query->where(static function (Builder $inner) use ($userId): void {
            $inner->where('created_by_id', $userId)
                ->orWhereHas('revisions', static function (Builder $revisions) use ($userId): void {
                    $revisions->where('created_by_id', $userId)
                        ->orWhereHas('submission', static function (Builder $submission) use ($userId): void {
                            $submission->where('requestor_id', $userId);
                        });
                });
        });
    }

    private function userOwnsOrRevisedDocument(TenantUser $user, ControlledDocument $document): bool
    {
        $userId = (string) $user->id;

        if ((string) ($document->created_by_id ?? '') === $userId) {
            return true;
        }

        return $document->revisions()
            ->where(static function (Builder $revisions) use ($userId): void {
                $revisions->where('created_by_id', $userId)
                    ->orWhereHas('submission', static function (Builder $submission) use ($userId): void {
                        $submission->where('requestor_id', $userId);
                    });
            })
            ->exists();
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

        return $user->hasAnyRole($policy->viewerRoles) || $user->hasAnyRole($policy->ownOnlyRoles);
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
