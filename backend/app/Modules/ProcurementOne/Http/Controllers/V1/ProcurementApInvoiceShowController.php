<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementApInvoiceRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementApInvoiceShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $apInvoice,
        ProcurementApInvoiceRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertApInvoicesEnabled();

        $model = $registry->find($apInvoice);
        abort_if($model === null, 404);

        return $this->ok($registry->toDetailPayload($model));
    }
}
