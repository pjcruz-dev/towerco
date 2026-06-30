<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementGrnRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementGrnService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementGrnUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $grn,
        ProcurementGrnService $service,
        ProcurementGrnRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);
        $planFeatures->assertGoodsReceiptEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($grn);
        abort_if($model === null, 404);

        $data = $request->validate([
            'project_id' => ['nullable', 'uuid'],
            'rollout_id' => ['nullable', 'uuid'],
            'site_id' => ['nullable', 'uuid'],
            'inventory_location_id' => ['nullable', 'uuid', 'exists:procurement_inventory_locations,id'],
            'gps_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'gps_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'received_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['sometimes', 'array'],
            'lines.*.po_line_id' => ['required_with:lines', 'uuid', 'exists:procurement_po_lines,id'],
            'lines.*.quantity_received' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.line_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $updated = $service->updateDraft($model, $data, $actor);
        $fresh = $registry->find((string) $updated->id);

        return $this->ok($registry->toDetailPayload($fresh ?? $updated));
    }
}
