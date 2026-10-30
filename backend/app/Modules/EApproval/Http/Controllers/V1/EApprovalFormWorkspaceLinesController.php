<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceLineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormWorkspaceLinesController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        string $slug,
        EApprovalFormWorkspaceLineService $lines,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'subsidiary' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
        ]);

        $status = (string) ($validated['status'] ?? $request->query('status', 'all'));
        $payload = $lines->paginate($slug, $request->user(), [
            'status' => $status === 'all' ? null : $status,
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'to' => isset($validated['to']) ? (string) $validated['to'] : null,
            'subsidiary' => isset($validated['subsidiary']) ? trim((string) $validated['subsidiary']) : null,
            'department' => isset($validated['department']) ? trim((string) $validated['department']) : null,
            'mine' => filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN),
            'search' => $query['search'],
            'awaiting_me' => filter_var($request->query('awaiting_me', false), FILTER_VALIDATE_BOOLEAN),
        ], $query['page'], $query['per_page']);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
