<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormWorkspaceShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $slug,
        EApprovalFormWorkspaceService $workspaces,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
            'subsidiary' => ['sometimes', 'nullable', 'string', 'max:64'],
            'department' => ['sometimes', 'nullable', 'string', 'max:120'],
            'mine' => ['sometimes'],
        ]);

        $forceOwn = filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN);
        $status = (string) ($validated['status'] ?? 'all');

        $filters = [
            'status' => $status === 'all' ? null : $status,
            'from' => isset($validated['from']) ? (string) $validated['from'] : null,
            'to' => isset($validated['to']) ? (string) $validated['to'] : null,
            'subsidiary' => isset($validated['subsidiary']) ? trim((string) $validated['subsidiary']) : null,
            'department' => isset($validated['department']) ? trim((string) $validated['department']) : null,
            'mine' => $forceOwn,
        ];

        return $this->ok($workspaces->buildDashboard($slug, $request->user(), $filters));
    }
}
