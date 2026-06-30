<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentRequestService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentRequestScheduleController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $paymentRequest,
        ProcurementPaymentRequestService $service,
        ProcurementPaymentRequestRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizePaymentsManage($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $service->find($paymentRequest);
        abort_if($model === null, 404);

        $validated = $request->validate([
            'scheduled_date' => ['sometimes', 'date'],
        ]);

        $scheduled = $service->schedule($model, $validated, $actor);

        return $this->ok(['payment_request' => $registry->toDetailPayload($scheduled)]);
    }
}
