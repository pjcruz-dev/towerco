<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementRfqRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqFromPrStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $pr,
        ProcurementRfqService $service,
        ProcurementRfqRegistryService $registry,
        ProcurementPrRegistryService $prRegistry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $purchaseRequisition = $prRegistry->find($pr);
        abort_if($purchaseRequisition === null, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'currency_code' => ['sometimes', 'string', 'max:8'],
            'vendor_ids' => ['sometimes', 'array'],
            'vendor_ids.*' => ['uuid'],
        ]);

        $rfq = $service->createFromPurchaseRequisition($purchaseRequisition, $validated, $actor);

        return $this->created(['rfq' => $registry->toDetailPayload($rfq)]);
    }
}
