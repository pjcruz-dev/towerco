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
        $canViewAll = $request->user()->can('e_approval:forms:manage');

        $paginator = $service->paginate(
            $request->user(),
            $query['page'],
            $query['per_page'],
            $query['search'],
            $status === 'all' ? null : $status,
            $canViewAll,
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
