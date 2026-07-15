<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteFileStoreController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, DocumentService $documents): JsonResponse
    {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'site_node_id' => ['required', 'uuid'],
            'file' => ['required', 'file'],
            'title' => ['nullable', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $document = $documents->upload(
            $site,
            $payload['site_node_id'],
            $request->file('file'),
            $request->user(),
            $payload['title'] ?? null,
            $payload['expires_at'] ?? null,
        );

        return $this->created($document);
    }
}
