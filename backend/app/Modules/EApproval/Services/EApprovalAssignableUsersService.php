<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Identity\Models\TenantUser;

final class EApprovalAssignableUsersService
{
    /**
     * Active tenant users for approver pickers (minimal fields, no permission dump).
     *
     * @return list<array{id: string, name: string, email: string, roles: list<string>}>
     */
    public function listForPickers(): array
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(static function (TenantUser $user): array {
                return [
                    'id' => (string) $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                ];
            })
            ->values()
            ->all();
    }
}
