<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantEnvironmentCloneService;
use Illuminate\Http\JsonResponse;

class CentralTenantEnvironmentCloneLatestController extends AbstractApiController
{
    public function __invoke(Tenant $tenant, TenantEnvironmentCloneService $clones): JsonResponse
    {
        $clone = $clones->latestForTenant($tenant);

        return $this->ok([
            'clone' => $clone !== null ? $clones->present($clone) : null,
        ]);
    }
}
