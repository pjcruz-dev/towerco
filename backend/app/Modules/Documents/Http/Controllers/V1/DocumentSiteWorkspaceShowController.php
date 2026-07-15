<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteWorkspaceShowController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, DocumentWorkspaceService $workspace): JsonResponse
    {
        abort_unless($request->user()?->can('documents:view'), 403);

        return $this->ok($workspace->workspacePayload($site));
    }
}
