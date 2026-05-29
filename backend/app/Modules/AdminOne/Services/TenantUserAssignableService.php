<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Services;

use App\Modules\Identity\Models\TenantUser;

class TenantUserAssignableService
{
    /**
     * Active tenant users for rollout owner pickers (minimal fields, no permission dump).
     *
     * @return list<array{id: string, name: string, email: string, roles: list<string>}>
     */
    public function listForRolloutAssignment(): array
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(static function (TenantUser $user): array {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
