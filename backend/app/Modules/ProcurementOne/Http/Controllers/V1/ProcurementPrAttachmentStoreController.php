<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Services\ProcurementPrAttachmentService;
use App\Modules\ProcurementOne\Services\ProcurementPrRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProcurementPrAttachmentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $pr,
        ProcurementPrAttachmentService $attachments,
        ProcurementPrRegistryService $registry,
    ): JsonResponse {
        abort_unless($request->user()?->can('procurement_one:documents:create'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = $registry->find($pr);
        abort_if($model === null, 404);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'field_name' => ['sometimes', 'string', 'max:64'],
        ]);

        $attachment = $attachments->store(
            $model,
            $data['file'],
            (string) ($data['field_name'] ?? 'quotes'),
            $actor,
        );

        return $this->created([
            'id' => (string) $attachment->id,
            'file_name' => $attachment->file_name,
            'field_name' => $attachment->field_name,
            'e_approval_attachment_id' => $attachment->e_approval_attachment_id,
        ]);
    }
}
