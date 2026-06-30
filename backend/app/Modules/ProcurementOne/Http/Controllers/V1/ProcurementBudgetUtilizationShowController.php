<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementBudgetUtilizationService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementBudgetUtilizationShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementBudgetUtilizationService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertModuleEnabled();

        $rolloutId = $request->query('rollout_id');
        $projectId = $request->query('project_id');

        if (is_string($rolloutId) && $rolloutId !== '') {
            return $this->ok($service->snapshotForRollout($rolloutId));
        }

        if (is_string($projectId) && $projectId !== '') {
            return $this->ok($service->snapshotForProject($projectId));
        }

        abort(422, __('rollout_id or project_id is required.'));
    }
}
