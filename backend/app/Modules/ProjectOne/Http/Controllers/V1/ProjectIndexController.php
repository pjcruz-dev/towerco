<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Services\ProjectIndexService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(Request $request, ProjectIndexService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:view'), 403);

        $query = $this->validatedTenantListQuery($request);
        $siteId = $request->query('site_id');
        $paginator = $service->paginate(
            $query['page'],
            $query['per_page'],
            $query['search'],
            is_string($siteId) ? $siteId : null,
        );
        $payload = $service->asPayload($paginator);

        return $this->okWithMeta($payload['data'], $payload['meta']);
    }
}
