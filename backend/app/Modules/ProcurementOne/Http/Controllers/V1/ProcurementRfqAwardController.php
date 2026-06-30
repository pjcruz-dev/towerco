<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementRfqRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqAwardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $rfq,
        ProcurementRfqService $service,
        ProcurementRfqRegistryService $registry,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:manage'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $service->find($rfq);
        abort_if($model === null, 404);

        $validated = $request->validate([
            'bid_id' => ['required', 'uuid'],
            'award_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $bid = ProcurementRfqBid::query()->find($validated['bid_id']);
        abort_if($bid === null, 404);

        $awarded = $service->award($model, $bid, $validated, $actor);

        return $this->ok(['rfq' => $registry->toDetailPayload($awarded)]);
    }
}
