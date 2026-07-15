<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\WorkspaceAuditIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceAuditIndexController extends AbstractApiController
{
    public function __invoke(Request $request, WorkspaceAuditIndexService $audit): JsonResponse
    {
        /** @var TenantUser|null $user */
        $user = $request->user();
        abort_unless($user?->can('workspace:audit:view'), 403);

        $validated = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'module' => ['sometimes', 'nullable', 'string', 'max:50'],
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $paginator = $audit->paginate(
            $user,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 50),
            $validated['module'] ?? null,
            $validated['search'] ?? null,
            $validated['from'] ?? null,
            $validated['to'] ?? null,
            $validated['sort'] ?? null,
        );

        return $this->okWithMeta($audit->asPayload($paginator), [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
