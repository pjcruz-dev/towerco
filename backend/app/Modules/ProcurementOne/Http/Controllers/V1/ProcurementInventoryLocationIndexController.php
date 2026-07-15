<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementInventoryLocationService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementInventoryLocationIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementInventoryLocationService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:inventory:view'), 403);
        $planFeatures->assertInventoryEnabled();

        $kind = $request->query('location_kind');

        return $this->ok([
            'locations' => $service->listActive(is_string($kind) ? $kind : null),
        ]);
    }
}
