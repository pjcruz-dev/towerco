<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, EApprovalFormService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $query = $this->validatedTenantListQuery($request);
        $status = $request->validate([
            'status' => ['sometimes', 'string', 'in:published,draft'],
        ])['status'] ?? null;

        $canViewAll = $user->can('e_approval:view') || $user->can('e_approval:forms:manage');
        $canPickPublished = $user->can('e_approval:submissions:create') && $status === 'published';

        abort_unless($canViewAll || $canPickPublished, 403);

        if (! $canViewAll && $status !== 'published') {
            abort(403);
        }

        $manageAll = $user->can('e_approval:forms:manage');
        $submissionPickerOnly = $canPickPublished && ! $canViewAll;

        $paginator = $service->paginate(
            $user,
            $query['page'],
            $query['per_page'],
            $query['search'],
            $manageAll,
            is_string($status) ? $status : null,
            $query['sort'],
            $submissionPickerOnly,
        );

        return $this->okWithMeta(
            $paginator->getCollection()->map(static fn (EApprovalForm $f) => $f->toListRow())->values()->all(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
