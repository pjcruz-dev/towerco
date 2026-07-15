<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementBudgetLineService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementBudgetLineDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $budgetLine,
        ProcurementBudgetLineService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeBudgetManage($request->user());
        $planFeatures->assertModuleEnabled();

        $model = $service->find($budgetLine);
        abort_if($model === null, 404);

        $service->delete($model);

        return $this->ok(['deleted' => true]);
    }
}
