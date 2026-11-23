<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Services\ApprovalDecisionService;
use App\Modules\EApproval\Support\EApprovalRoleFieldAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalApprovalIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, ApprovalDecisionService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:approve') || $request->user()?->can('e_approval:submissions:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $status = (string) $request->query('status', 'pending');
        $awaitingMe = filter_var($request->query('awaiting_me', false), FILTER_VALIDATE_BOOLEAN);

        $paginator = $service->paginate(
            $request->user(),
            $query['page'],
            $query['per_page'],
            $status,
            $awaitingMe,
            $query['sort'],
        );

        $roleAccess = app(EApprovalRoleFieldAccess::class);
        abort_unless($roleAccess->allows($request->user(), 'view'), 403);

        return $this->okWithMeta(
            $paginator->getCollection()->map(function (EApprovalRequestApproval $approval) use ($roleAccess, $request) {
                $row = $approval->toListRow();
                if (isset($row['submission']) && is_array($row['submission'])) {
                    $row['submission'] = $roleAccess->redactListRow($request->user(), $row['submission']);
                }

                return $row;
            })->values()->all(),
            [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        );
    }
}
