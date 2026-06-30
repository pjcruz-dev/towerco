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

final class ProcurementBudgetLineStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementBudgetLineService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeBudgetManage($request->user());
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'rollout_id' => ['nullable', 'uuid'],
            'cost_center_id' => ['nullable', 'uuid', 'exists:procurement_cost_centers,id'],
            'line_code' => ['nullable', 'string', 'max:64'],
            'description' => ['required', 'string', 'max:500'],
            'expense_type' => ['sometimes', 'string', 'in:'.implode(',', ProcurementExpenseType::all())],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $line = $service->create($data);

        return $this->created($service->asPayload($line));
    }
}
