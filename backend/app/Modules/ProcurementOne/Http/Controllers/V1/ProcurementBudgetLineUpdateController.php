<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementBudgetLineService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementExpenseType;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementBudgetLineUpdateController extends AbstractApiController
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

        $data = $request->validate([
            'cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'line_code' => ['nullable', 'string', 'max:64'],
            'description' => ['sometimes', 'string', 'max:500'],
            'expense_type' => ['sometimes', 'string', 'in:'.implode(',', ProcurementExpenseType::all())],
            'budget_amount' => ['sometimes', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->ok($service->asPayload($service->update($model, $data)));
    }
}
