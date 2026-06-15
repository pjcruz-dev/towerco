<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;

final class PlatformTenantUserIndexService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listActive(Tenant $tenant, int $limit = 100): array
    {
        return $tenant->run(function () use ($limit): array {
            return TenantUser::query()
                ->with('roles:id,name')
                ->where('is_active', true)
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(static fn (TenantUser $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->getRoleNames()->values()->all(),
                    'is_active' => $user->isActive(),
                ])
                ->values()
                ->all();
        });
    }
}
