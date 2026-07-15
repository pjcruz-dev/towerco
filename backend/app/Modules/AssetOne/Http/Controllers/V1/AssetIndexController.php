<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetOne\Services\AssetIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, AssetIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('asset_one:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $paginator = $service->paginate($query['page'], $query['per_page'], $query['search'], $query['sort']);
        $payload = $service->asPayload($paginator);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
