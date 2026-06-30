<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementRfqBidService;
use App\Modules\ProcurementOne\Services\ProcurementRfqRegistryService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementRfqBidStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $rfq,
        ProcurementRfqService $rfqService,
        ProcurementRfqBidService $bidService,
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
            'vendor_id' => ['required', 'uuid'],
            'validity_until' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'currency_code' => ['sometimes', 'string', 'max:8'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.rfq_line_id' => ['required', 'uuid'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
            'lines.*.unit_price' => ['sometimes', 'numeric', 'min:0'],
            'lines.*.monthly_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.yearly_unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'lines.*.lead_time_days' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'lines.*.notes' => ['sometimes', 'nullable', 'string', 'max:500'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:10240'],
        ]);

        $attachmentFiles = array_values(array_filter(
            $request->file('attachments', []) ?? [],
            static fn ($file) => $file instanceof \Illuminate\Http\UploadedFile,
        ));

        $bid = $bidService->capture($model, $validated, $actor, 'internal', null, $attachmentFiles);
        $refreshed = $rfqService->find($rfq);

        return $this->created([
            'bid' => $registry->bidPayload($bid),
            'rfq' => $refreshed !== null ? $registry->toDetailPayload($refreshed) : null,
        ]);
    }
}
