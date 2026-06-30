<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementGrnPrintService;
use App\Modules\ProcurementOne\Services\ProcurementGrnRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementGrnPrintController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $grn,
        ProcurementGrnRegistryService $registry,
        ProcurementGrnPrintService $print,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertGoodsReceiptEnabled();

        $model = $registry->find($grn);
        abort_if($model === null, 404);

        return $this->ok($print->build($model));
    }
}
