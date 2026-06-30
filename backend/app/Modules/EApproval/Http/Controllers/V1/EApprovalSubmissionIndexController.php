<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, EApprovalSubmissionService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $status = (string) $request->query('status', 'all');

        // Only auditors see every user's submissions by default.
        // Form managers (e_approval:forms:manage) can manage forms but are scoped
        // to their own submissions + those they approve, unless they also hold the
        // audit:view permission.
        $canViewAll = $request->user()->can('e_approval:audit:view');

        // ?mine=1 forces the current-user-only scope regardless of canViewAll,
        // so admins can filter to their own submissions.
        $forceOwn = filter_var($request->query('mine', false), FILTER_VALIDATE_BOOLEAN);

        $paginator = $service->paginate(
            $request->user(),
            $query['page'],
            $query['per_page'],
            $query['search'],
            $status === 'all' ? null : $status,
            $canViewAll && ! $forceOwn,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn (EApprovalSubmission $s) => $s->toListRow())->values()->all(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
