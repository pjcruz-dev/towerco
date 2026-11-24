<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Support;

use App\Modules\AdminOne\Models\TenantRole;
use App\Modules\AdminOne\Support\RoleAccessMatrix;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Support\TenantRbacSystemRoles;
use Illuminate\Support\Facades\Schema;

/**
 * Page access for Ticketing. A missing row stays open, so another module's matrix does not lock tickets.
 */
final class TicketingRoleFieldAccess
{
    public const ENTITY = 'ticketing_ticket';

    /**
     * @return array<string, mixed>
     */
    public function matrixFor(TenantUser $user): array
    {
        if ($user->hasRole(TenantRbacSystemRoles::FULL_ADMIN)) {
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

    /**
     * @param  array<string, mixed>  $matrix
     */
    public function navTargetHiddenIn(array $matrix, string $path): bool
    {
        $fields = $matrix['fields'][self::ENTITY] ?? null;
        if (! is_array($fields)) {
            return false;
        }

        $path = explode('?', $path, 2)[0];
        $path = explode('#', $path, 2)[0];
        $targets = [
            '/ticketing',
            '/ticketing/tickets',
            '/ticketing/tickets/new',
            '/ticketing/reports',
            '/ticketing/fields',
        ];

        foreach ($targets as $target) {
            if (($fields['nav:'.$target] ?? null) !== 'hide') {
                continue;
            }
            if ($target === '/ticketing') {
                if ($path === '/ticketing' || $path === '/ticketing/') {
                    return true;
                }

                continue;
            }
            if ($target === '/ticketing/tickets') {
                if (($path === $target || str_starts_with($path, $target.'/')) && ! str_starts_with($path, $target.'/new')) {
                    return true;
                }

                continue;
            }
            if ($path === $target || str_starts_with($path, $target.'/')) {
                return true;
            }
        }

        return false;
    }

    public function navTargetHidden(TenantUser $user, string $path): bool
    {
        return $this->navTargetHiddenIn($this->matrixFor($user), $path);
    }
}
