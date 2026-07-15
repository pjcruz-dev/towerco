<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementGrnRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementGrnService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementPoRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementGrnFromPoStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $po,
        ProcurementGrnService $service,
        ProcurementGrnRegistryService $registry,
        ProcurementPoRegistryService $poRegistry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertGoodsReceiptEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $poRegistry->find($po);
        abort_if($model === null, 404);

        $data = $this->validatedPayload($request);
        $result = $service->createFromPurchaseOrder($model, $data, $actor, (bool) ($data['post'] ?? false));
        $fresh = $registry->find((string) $result['grn']->id);

        return $this->created([
            'grn' => $registry->toDetailPayload($fresh ?? $result['grn']),
            'warning' => $result['warning'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'post' => ['sometimes', 'boolean'],
            'project_id' => ['nullable', 'uuid'],
            'rollout_id' => ['nullable', 'uuid'],
            'site_id' => ['nullable', 'uuid'],
            'gps_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['sometimes', 'array'],
            'lines.*.po_line_id' => ['required_with:lines', 'uuid', 'exists:procurement_po_lines,id'],
            'lines.*.quantity_received' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:500'],
            'inventory_location_id' => ['nullable', 'uuid', 'exists:procurement_inventory_locations,id'],
        ]);
    }
}
