<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteFileReorderController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, DocumentService $documents): JsonResponse
    {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'node_id' => ['required', 'uuid'],
            'order' => ['required', 'array', 'min:1'],
            'order.*' => ['uuid'],
        ]);

        $documents->reorder($site, $payload['node_id'], $payload['order'], $request->user());

        return $this->ok(['reordered' => true]);
    }
}
