<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Modules\ProcurementOne\Models\ProcurementRfqBid;
use App\Modules\ProcurementOne\Models\ProcurementRfqBidAttachment;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementRfqBidAttachmentService;
use App\Modules\ProcurementOne\Services\ProcurementRfqService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementRfqBidAttachmentDownloadController
{
    public function __invoke(
        Request $request,
        string $rfq,
        string $bid,
        string $attachment,
        ProcurementRfqService $rfqService,
        ProcurementRfqBidAttachmentService $files,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): StreamedResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertRfqSourcingEnabled();

        $model = $rfqService->find($rfq);
        abort_if($model === null, 404);

        $bidModel = ProcurementRfqBid::query()
            ->where('rfq_id', $model->id)
            ->where('id', $bid)
            ->first();
        abort_if($bidModel === null, 404);

        $attachmentModel = ProcurementRfqBidAttachment::query()
            ->where('bid_id', $bidModel->id)
            ->where('id', $attachment)
            ->firstOrFail();

        return $files->download($attachmentModel);
    }
}
