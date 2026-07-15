<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteFileIndexController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, DocumentService $documents): JsonResponse
    {
        abort_unless($request->user()?->can('documents:view'), 403);

        $payload = $request->validate([
            'node_id' => ['required', 'uuid'],
        ]);

        return $this->ok([
            'items' => $documents->listForNode($site, $payload['node_id']),
        ]);
    }
}
