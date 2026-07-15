<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentBatchService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentBatchShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $paymentBatch,
        ProcurementPaymentBatchService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $batch = $service->find($paymentBatch);
        abort_if($batch === null, 404);

        return $this->ok(['payment_batch' => $service->toDetailPayload($batch)]);
    }
}
