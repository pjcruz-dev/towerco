<?php

declare(strict_types=1);

namespace App\Modules\FiberOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\FiberOne\Services\FiberRouteIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiberRouteIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, FiberRouteIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('fiber_one:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $paginator = $service->paginate($query['page'], $query['per_page'], $query['search'], $query['sort']);
        $payload = $service->asPayload($paginator);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
