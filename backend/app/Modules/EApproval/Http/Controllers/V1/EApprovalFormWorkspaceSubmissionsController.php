<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceService;
use App\Modules\EApproval\Services\EApprovalFormWorkspaceSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormWorkspaceSubmissionsController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        string $slug,
        EApprovalFormWorkspaceService $workspaces,
        EApprovalFormWorkspaceSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:view'), 403);
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $context = $workspaces->resolveWorkspaceContext($slug, $request->user());
        $query = $this->validatedTenantListQuery($request);
        $validated = $request->validate([
            'status' => ['sometimes', 'string', 'max:50'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date'],
        ]);

        $forceOwn = filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN);
        $canViewAll = $workspaces->viewerCanSeeAllInWorkspace($request->user(), $context['workspace']) && ! $forceOwn;
        $status = (string) ($validated['status'] ?? $request->query('status', 'all'));

        $payload = $submissions->paginate(
            $context['form'],
            $context['workspace'],
            $context['form_ids'],
            $request->user(),
            $canViewAll,
            $query['page'],
            $query['per_page'],
            $query['search'],
            $status === 'all' ? null : $status,
            isset($validated['from']) ? (string) $validated['from'] : null,
            isset($validated['to']) ? (string) $validated['to'] : null,
            $query['sort'],
        );

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
