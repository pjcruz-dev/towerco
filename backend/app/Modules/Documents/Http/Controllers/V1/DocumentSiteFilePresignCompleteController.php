<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentPresignedUploadService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteFilePresignCompleteController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        Site $site,
        DocumentPresignedUploadService $presigned,
    ): JsonResponse {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'upload_token' => ['required', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $document = $presigned->completeIntent(
            $site,
            $payload['upload_token'],
            $request->user(),
            $payload['title'] ?? null,
            $payload['expires_at'] ?? null,
        );

        return $this->created($document);
    }
}
