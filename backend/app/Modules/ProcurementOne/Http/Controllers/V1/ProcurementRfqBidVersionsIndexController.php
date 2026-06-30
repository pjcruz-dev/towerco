<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementRfqBidVersionService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqBidVersionsIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $rfq,
        string $bid,
        ProcurementRfqService $rfqService,
        ProcurementRfqBidVersionService $versions,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $model = $rfqService->find($rfq);
        abort_if($model === null, 404);

        $bidModel = ProcurementRfqBid::query()
            ->where('rfq_id', $model->id)
            ->where('id', $bid)
            ->with(['versions.lines.rfqLine', 'versions.attachments'])
            ->first();
        abort_if($bidModel === null, 404);

        return $this->ok([
            'versions' => $bidModel->versions
                ->sortBy('version_no')
                ->values()
                ->map(fn ($version) => $versions->versionPayload($version))
                ->all(),
        ]);
    }
}
