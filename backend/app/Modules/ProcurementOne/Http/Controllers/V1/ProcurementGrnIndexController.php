<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementGrnRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementGrnIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementGrnRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertGoodsReceiptEnabled();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $paginator = $registry->paginate(
            $page,
            $perPage,
            $request->string('search')->toString() ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('po_id')->toString() ?: null,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn ($grn) => $registry->toListPayload($grn))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
