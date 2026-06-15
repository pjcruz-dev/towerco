<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, TenantUserIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        $query = $this->validatedTenantListQuery($request);
        $status = $request->validate([
            'status' => ['sometimes', 'string', 'in:active,inactive,all'],
        ])['status'] ?? null;
        $status = $status === 'all' ? null : $status;

        $paginator = $service->paginate($query['page'], $query['per_page'], $query['search'], $status);
        /** @var \App\Modules\Identity\Models\TenantUser $viewer */
        $viewer = $request->user();
        $payload = $service->asPayload($paginator, $viewer);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
