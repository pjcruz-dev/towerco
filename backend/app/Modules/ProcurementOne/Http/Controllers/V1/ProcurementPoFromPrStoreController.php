<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementFormComposeService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementPoService;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPoFromPrStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $pr,
        ProcurementFormComposeService $compose,
        ProcurementPoService $service,
        ProcurementPoRegistryService $registry,
        ProcurementPrRegistryService $prRegistry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertModuleEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $purchaseRequisition = $prRegistry->find($pr);
        abort_if($purchaseRequisition === null, 404);

        if ($request->has('values')) {
            $data = $request->validate([
                'values' => ['required', 'array'],
            ]);

            $po = $compose->createPurchaseOrder(
                $data['values'],
                $actor,
                null,
                $purchaseRequisition,
                requireRequired: false,
            );
            $model = $registry->find((string) $po->id);

            return $this->created($registry->toDetailPayload($model ?? $po));
        }

        $data = $request->validate([
            'vendor_code' => ['nullable', 'string', 'max:64'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'supplier' => ['nullable', 'string', 'max:500'],
            'ship_to' => ['nullable', 'string', 'max:2000'],
            'delivery_date' => ['nullable', 'date'],
            'payment_terms' => ['nullable', 'string', 'max:120'],
            'currency_code' => ['nullable', 'string', 'max:8'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'vat_exempt_amount' => ['nullable', 'numeric', 'min:0'],
            'zero_rated_amount' => ['nullable', 'numeric', 'min:0'],
            'vat_rate' => ['nullable', 'numeric', 'min:0'],
            'less_discount' => ['nullable', 'numeric', 'min:0'],
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.item' => ['nullable', 'string', 'max:120'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:500'],
            'lines.*.uom' => ['nullable', 'string', 'max:32'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $po = $service->createFromPurchaseRequisition($purchaseRequisition, $data, $actor);
        $model = $registry->find((string) $po->id);

        return $this->created($registry->toDetailPayload($model ?? $po));
    }
}
