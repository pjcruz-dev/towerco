<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementContractRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementContractService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementContractIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementContractService $service,
        ProcurementContractRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertVendorContractsEnabled();

        $page = max(1, (int) $request->integer('page', 1));
        $perPage = min(100, max(1, (int) $request->integer('per_page', 25)));

        $paginator = $service->paginate(
            $page,
            $perPage,
            $request->string('search')->toString() ?: null,
            $request->string('status')->toString() ?: null,
            $request->string('vendor_id')->toString() ?: null,
            $request->string('site_id')->toString() ?: null,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn ($row) => $registry->toListPayload($row))->values()->all(),
            [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
