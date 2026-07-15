<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Services\ProcurementInventoryLocationService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementInventoryLocationKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementInventoryLocationUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $location,
        ProcurementInventoryLocationService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:inventory:manage'), 403);
        $planFeatures->assertInventoryEnabled();

        $model = $service->find($location);
        abort_if($model === null, 404);

        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:32'],
            'name' => ['sometimes', 'string', 'max:120'],
            'location_kind' => ['sometimes', 'string', 'in:'.implode(',', ProcurementInventoryLocationKind::all())],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'is_default_receipt' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $service->update($model, array_merge([
            'code' => $model->code,
            'name' => $model->name,
            'location_kind' => $model->location_kind,
            'site_id' => $model->site_id,
            'is_default_receipt' => $model->is_default_receipt,
            'is_active' => $model->is_active,
            'notes' => $model->notes,
        ], $data));

        return $this->ok($service->asPayload($updated));
    }
}
