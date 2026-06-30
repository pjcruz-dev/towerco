<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteWorkspaceUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, DocumentWorkspaceService $workspace): JsonResponse
    {
        abort_unless($request->user()?->can('documents:manage'), 403);

        $payload = $request->validate([
            'rollout_program_id' => ['nullable', 'uuid'],
        ]);

        $workspace->updateWorkspace($site, $payload['rollout_program_id'] ?? null);

        return $this->ok($workspace->workspacePayload($site));
    }
}
