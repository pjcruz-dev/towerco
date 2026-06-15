<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\PlatformTenantAuditIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CentralPlatformAuditIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        PlatformTenantAuditIndexService $audit,
    ): JsonResponse {
        $limit = min(50, max(1, (int) $request->query('limit', 20)));

        return $this->ok($audit->recent($limit));
    }
}
