<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementInventoryStockService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementInventoryDeployStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementInventoryStockService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:inventory:manage'), 403);
        $planFeatures->assertInventoryEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'from_location_id' => ['required', 'uuid', 'exists:procurement_inventory_locations,id'],
            'to_location_id' => ['required', 'uuid', 'exists:procurement_inventory_locations,id'],
            'po_line_id' => ['required', 'uuid', 'exists:procurement_po_lines,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'create_asset' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'asset' => ['sometimes', 'array'],
            'asset.name' => ['sometimes', 'string', 'max:200'],
            'asset.category' => ['sometimes', 'string', 'max:64'],
            'asset.rfid_tag' => ['nullable', 'string', 'max:120'],
            'asset.warranty_expiry' => ['nullable', 'date'],
            'asset.purchase_value' => ['nullable', 'numeric', 'min:0'],
        ]);

        return $this->created($service->deploy($data, $actor));
    }
}
