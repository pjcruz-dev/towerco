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

final class ProcurementGrnAttachmentStoreController extends AbstractApiController
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
            'file' => ['required', 'file', 'max:10240'],
            'field_name' => ['sometimes', 'string', 'max:64'],
        ]);

        $updated = $service->storeAttachment(
            $model,
            $data['file'],
            (string) ($data['field_name'] ?? 'delivery_photo'),
            $actor,
        );

        $attachment = $updated->attachments->last();

        return $this->created([
            'id' => $attachment ? (string) $attachment->id : null,
            'file_name' => $attachment?->file_name,
            'field_name' => $attachment?->field_name,
        ]);
    }
}
