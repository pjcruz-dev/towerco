<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementContractExpiringService;
use App\Modules\ProcurementOne\Services\ProcurementContractRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementContractExpiringIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementContractExpiringService $expiring,
        ProcurementContractRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertVendorContractsEnabled();

        $withinDays = min(365, max(1, (int) $request->integer('within_days', 90)));
        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        if ($request->boolean('summary_only')) {
            return $this->ok([
                'summary' => $expiring->summaryCounts(),
                'rows' => $expiring->list($withinDays),
            ]);
        }

        $paginator = $expiring->paginateExpiring($page, $perPage, $withinDays);

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn ($row) => $registry->toListPayload($row))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'summary' => $expiring->summaryCounts(),
                'within_days' => $withinDays,
            ],
        );
    }
}
