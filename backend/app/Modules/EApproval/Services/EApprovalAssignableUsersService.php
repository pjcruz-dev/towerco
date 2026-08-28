<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Identity\Models\TenantUser;

final class EApprovalAssignableUsersService
{
    /**
     * Active users who can approve (permission e_approval:approve) for form/workflow pickers.
     * Includes e_approval_approver, e_approval_admin, tenant_admin, and other roles granted approve.
     * Excludes bootstrap break-glass admin (password_login_exempt).
     *
     * @return list<array{id: string, name: string, email: string, roles: list<string>}>
     */
    public function listForPickers(): array
    {
        return TenantUser::query()
            ->where('is_active', true)
            ->where(static function ($query): void {
                $query->where('password_login_exempt', false)
                    ->orWhereNull('password_login_exempt');
            })
            ->permission('e_approval:approve')
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
