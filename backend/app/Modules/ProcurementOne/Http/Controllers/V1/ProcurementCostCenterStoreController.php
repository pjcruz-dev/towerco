<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementCostCenterService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementCostCenterStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementCostCenterService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->created($service->asPayload($service->create($data)));
    }
}
