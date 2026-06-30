<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentWorkspaceService;
use App\Modules\Sites\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentSiteLessorStoreController extends AbstractApiController
{
    public function __invoke(Request $request, Site $site, DocumentWorkspaceService $workspace): JsonResponse
    {
        abort_unless($request->user()?->can('documents:upload'), 403);

        $payload = $request->validate([
            'lessor_name' => ['required', 'string', 'max:255'],
            'lessor_contact' => ['nullable', 'string', 'max:255'],
        ]);

        return $this->created($workspace->addLessor(
            $site,
            $payload['lessor_name'],
            $payload['lessor_contact'] ?? null,
        ));
    }
}
