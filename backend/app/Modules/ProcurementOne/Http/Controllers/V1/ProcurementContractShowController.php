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

final class ProcurementContractShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $contract,
        ProcurementContractService $service,
        ProcurementContractRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertVendorContractsEnabled();

        $model = $service->find($contract);
        abort_if($model === null, 404);

        return $this->ok(['contract' => $registry->toDetailPayload($model)]);
    }
}
