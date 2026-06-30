<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentRequestShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $paymentRequest,
        ProcurementPaymentRequestService $service,
        ProcurementPaymentRequestRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizeView($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $found = $service->find($paymentRequest);
        abort_if($found === null, 404);

        return $this->ok(['payment_request' => $registry->toDetailPayload($found)]);
    }
}
