<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementInventoryLocationService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Support\ProcurementInventoryLocationKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementInventoryLocationStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        ProcurementInventoryLocationService $service,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:inventory:manage'), 403);
        $planFeatures->assertInventoryEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'name' => ['required', 'string', 'max:120'],
            'location_kind' => ['required', 'string', 'in:'.implode(',', ProcurementInventoryLocationKind::all())],
            'site_id' => ['nullable', 'uuid', 'exists:sites,id'],
            'is_default_receipt' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $location = $service->create($data, $actor);

        return $this->created($service->asPayload($location));
    }
}
