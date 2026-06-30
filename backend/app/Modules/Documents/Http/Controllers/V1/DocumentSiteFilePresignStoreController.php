<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentPresignedUploadService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteFilePresignStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Site $site,
        DocumentPresignedUploadService $presigned,
    ): JsonResponse {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'site_node_id' => ['required', 'uuid'],
            'filename' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:120'],
            'size_bytes' => ['required', 'integer', 'min:1'],
        ]);

        $intent = $presigned->createIntent(
            $site,
            $payload['site_node_id'],
            $payload['filename'],
            $payload['mime_type'],
            (int) $payload['size_bytes'],
            $request->user(),
        );

        return $this->created($intent);
    }
}
