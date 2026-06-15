<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;

final class TenantModuleRbacSyncService
{
    public function __construct(
        private readonly TenantRbacBaselineService $rbacBaseline,
    ) {}

    public function syncForTenant(Tenant $tenant): void
    {
        $tenant->run(function (): void {
            $this->rbacBaseline->ensure();
        });
    }
}
