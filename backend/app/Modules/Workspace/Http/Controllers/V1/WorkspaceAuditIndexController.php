<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\WorkspaceAuditIndexService;
use App\Modules\Workspace\Support\WorkspaceAuditTaxonomy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'actor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'from' => ['sometimes', 'nullable', 'date'],
            'to' => ['sometimes', 'nullable', 'date'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:64'],
            'category' => ['sometimes', 'nullable', 'string', Rule::in([...WorkspaceAuditTaxonomy::categories(), 'all'])],
            'severity' => ['sometimes', 'nullable', 'string', Rule::in([...WorkspaceAuditTaxonomy::severities(), 'all'])],
            'action_family' => ['sometimes', 'nullable', 'string', 'max:50'],
            'entity_type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'entity_id' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $paginator = $audit->paginate(
            $user,
            (int) ($validated['page'] ?? 1),
            (int) ($validated['per_page'] ?? 50),
            [
                'module' => $validated['module'] ?? null,
                'search' => $validated['search'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'sort' => $validated['sort'] ?? null,
                'actor' => $validated['actor'] ?? null,
                'category' => $validated['category'] ?? null,
                'severity' => $validated['severity'] ?? null,
                'action_family' => $validated['action_family'] ?? null,
                'entity_type' => $validated['entity_type'] ?? null,
                'entity_id' => $validated['entity_id'] ?? null,
            ],
        );

        return $this->okWithMeta($audit->asPayload($paginator), [
            'total' => $paginator->total(),
            'per_page' => $paginator->perPage(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
