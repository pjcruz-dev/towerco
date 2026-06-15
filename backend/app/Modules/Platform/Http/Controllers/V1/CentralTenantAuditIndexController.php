<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\Tenant;
use App\Modules\Platform\Services\PlatformTenantAuditIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralTenantAuditIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Tenant $tenant,
        PlatformTenantAuditIndexService $audit,
    ): JsonResponse {
        $limit = min(100, max(1, (int) $request->query('limit', 50)));

        return $this->ok($audit->forTenant($tenant, $limit));
    }
}
