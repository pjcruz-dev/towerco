<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementRfqComparisonService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqComparisonShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $rfq,
        ProcurementRfqService $service,
        ProcurementRfqComparisonService $comparison,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $model = $service->find($rfq);
        abort_if($model === null, 404);

        return $this->ok($comparison->matrix($model));
    }
}
