<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOneDashboardService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementOneDashboardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementOneDashboardService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertModuleEnabled();

        return $this->ok($service->build($request->user()));
    }
}
