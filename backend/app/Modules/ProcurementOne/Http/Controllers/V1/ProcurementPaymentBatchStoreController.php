<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPaymentBatchService;
use Illuminate\Http\JsonResponse;
use App\Modules\ProcurementOne\Support\FinanceOneAccess;
use Illuminate\Http\Request;

final class ProcurementPaymentBatchStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementPaymentBatchService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizePaymentsManage($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $validated = $request->validate([
            'payment_request_ids' => ['required', 'array', 'min:1'],
            'payment_request_ids.*' => ['uuid'],
            'scheduled_date' => ['sometimes', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $batch = $service->createFromRequests($validated['payment_request_ids'], $validated, $actor);

        return $this->created(['payment_batch' => $service->toDetailPayload($batch)]);
    }
}
