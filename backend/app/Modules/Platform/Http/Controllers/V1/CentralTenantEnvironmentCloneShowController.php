<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Models\TenantEnvironmentClone;
use App\Modules\Tenancy\Services\TenantEnvironmentCloneService;
use Illuminate\Http\JsonResponse;

class CentralTenantEnvironmentCloneShowController extends AbstractApiController
{
    public function __invoke(
        Tenant $tenant,
        TenantEnvironmentClone $clone,
        TenantEnvironmentCloneService $clones,
    ): JsonResponse {
        $clones->findForTenant($tenant, (string) $clone->id);

        return $this->ok($clones->present($clone->fresh() ?? $clone));
    }
}
