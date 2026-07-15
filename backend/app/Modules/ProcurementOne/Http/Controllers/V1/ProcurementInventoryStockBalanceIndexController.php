<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementInventoryStockService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementInventoryStockBalanceIndexController extends AbstractApiController
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

        $paginator = $service->paginateBalances(
            $query['page'],
            $query['per_page'],
            is_string($locationId) ? $locationId : null,
            $query['search'],
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(fn ($row) => $service->balanceAsPayload($row))->values()->all(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
