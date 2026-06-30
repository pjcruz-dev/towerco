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

final class ProcurementPaymentBatchMarkReconciledController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $paymentBatch,
        ProcurementPaymentBatchService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        FinanceOneAccess::authorizePaymentsManage($request->user());
        $planFeatures->assertPaymentTrackingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $batch = $service->find($paymentBatch);
        abort_if($batch === null, 404);

        $reconciled = $service->markReconciled($batch, $actor);

        return $this->ok(['payment_batch' => $service->toDetailPayload($reconciled)]);
    }
}
