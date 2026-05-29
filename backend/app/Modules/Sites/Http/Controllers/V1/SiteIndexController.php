<?php

declare(strict_types=1);

namespace App\Modules\Sites\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Sites\Services\SiteIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, SiteIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('sites:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $paginator = $service->paginate($query['page'], $query['per_page'], $query['search']);
        $payload = $service->asPayload($paginator);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
