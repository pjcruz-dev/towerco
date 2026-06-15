<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\PlatformTenantUserIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantUserIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        PlatformTenantUserIndexService $users,
    ): JsonResponse {
        $limit = min(200, max(1, (int) $request->query('limit', 100)));

        return $this->ok($users->listActive($tenant, $limit));
    }
}
