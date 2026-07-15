<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Identity\Models\TenantUser;

final class EApprovalRoleApproverResolver
{
    public function resolveFirstApproverForRole(string $roleName): ?string
    {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return null;
        }

        /** @var TenantUser|null $user */
        $user = TenantUser::query()
            ->where('is_active', true)
            ->whereHas('roles', static fn ($query) => $query->where('name', $roleName))
            ->orderBy('name')
            ->first();

        if (! $user instanceof TenantUser) {
            return null;
        }

        if (! $user->can('e_approval:approve')) {
            return null;
        }

        return (string) $user->id;
    }
}
