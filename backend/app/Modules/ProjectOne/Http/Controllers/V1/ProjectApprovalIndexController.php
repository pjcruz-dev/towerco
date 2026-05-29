<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Services\ProjectApprovalIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectApprovalIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, ProjectApprovalIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $status = (string) $request->query('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $paginator = $service->paginate($query['page'], $query['per_page'], $query['search'], $status);
        $payload = $service->asPayload($paginator);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
