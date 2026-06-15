<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\WorkspaceSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WorkspaceSearchController extends AbstractApiController
{
    public function __invoke(Request $request, WorkspaceSearchService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof TenantUser && $user->can('dashboard:view'), 403);

        $validated = $request->validate([
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ]);

        $query = Str::limit(trim((string) ($validated['q'] ?? '')), 255, '');
        $limit = (int) ($validated['limit'] ?? 5);

        return $this->ok($service->search($user, $query, $limit));
    }
}
