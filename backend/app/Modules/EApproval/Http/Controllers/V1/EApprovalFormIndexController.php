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
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $status = $request->validate([
            'status' => ['sometimes', 'string', 'in:published,draft'],
        ])['status'] ?? null;

        $manageAll = $request->user()->can('e_approval:forms:manage');
        $paginator = $service->paginate(
            $request->user(),
            $query['page'],
            $query['per_page'],
            $query['search'],
            $manageAll,
            is_string($status) ? $status : null,
            $query['sort'],
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
