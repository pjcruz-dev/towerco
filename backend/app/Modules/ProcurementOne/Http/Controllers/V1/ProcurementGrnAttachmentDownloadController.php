<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProcurementOne\Models\ProcurementGrnAttachment;
use App\Modules\ProcurementOne\Services\ProcurementGrnFileStorageService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ProcurementGrnAttachmentDownloadController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $grn,
        string $attachment,
        ProcurementGrnFileStorageService $storage,
        ProcurementOnePlanFeaturesService $planFeatures,
    ): StreamedResponse {
        abort_unless($request->user()?->can('procurement_one:view'), 403);
        $planFeatures->assertGoodsReceiptEnabled();

        $model = ProcurementGrnAttachment::query()
            ->where('grn_id', $grn)
            ->where('id', $attachment)
            ->firstOrFail();

        return $storage->download($model);
    }
}
