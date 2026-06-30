<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementBudgetLineService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementBudgetLineIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementBudgetLineService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertModuleEnabled();

        $rolloutId = $request->query('rollout_id');
        $projectId = $request->query('project_id');

        return $this->ok([
            'budget_lines' => $service->listForScope(
                is_string($rolloutId) ? $rolloutId : null,
                is_string($projectId) ? $projectId : null,
            ),
        ]);
    }
}
