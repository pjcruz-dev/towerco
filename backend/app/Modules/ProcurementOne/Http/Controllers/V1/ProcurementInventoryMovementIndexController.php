<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementInventoryStockService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementInventoryMovementType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementInventoryMovementIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        ProcurementInventoryStockService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:inventory:view'), 403);
        $planFeatures->assertInventoryEnabled();

        $query = $this->validatedTenantListQuery($request);
        $locationId = $request->query('location_id');
        $grnId = $request->query('grn_id');
        $movementType = $request->query('movement_type');
        if (is_string($movementType) && $movementType !== '' && $movementType !== 'all'
            && ! in_array($movementType, ProcurementInventoryMovementType::all(), true)) {
            abort(422, __('Movement type is invalid.'));
        }

        $paginator = $service->paginateMovements(
            $query['page'],
            $query['per_page'],
            is_string($locationId) ? $locationId : null,
            is_string($grnId) ? $grnId : null,
            is_string($movementType) ? $movementType : null,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(fn ($row) => $service->asMovementPayload($row))->values()->all(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
