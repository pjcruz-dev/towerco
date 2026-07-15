<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementRfqPoBridgeService;
use App\Modules\ProcurementOne\Services\ProcurementRfqRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqPoStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $rfq,
        ProcurementRfqService $rfqService,
        ProcurementRfqPoBridgeService $bridge,
        ProcurementPoRegistryService $poRegistry,
        ProcurementRfqRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $rfqService->find($rfq);
        abort_if($model === null, 404);

        $validated = $request->validate([
            'ship_to' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'delivery_date' => ['sometimes', 'nullable', 'date'],
            'payment_terms' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $po = $bridge->createPurchaseOrderFromAward($model, $validated, $actor);
        $poModel = $poRegistry->find((string) $po->id);
        $refreshedRfq = $rfqService->find($rfq);

        return $this->created([
            'purchase_order' => $poRegistry->toDetailPayload($poModel ?? $po),
            'rfq' => $refreshedRfq !== null ? $registry->toDetailPayload($refreshedRfq) : null,
        ]);
    }
}
