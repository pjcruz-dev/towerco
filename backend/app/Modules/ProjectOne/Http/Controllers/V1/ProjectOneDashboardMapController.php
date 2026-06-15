<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\ProjectOne\Services\ProjectOneMapDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ProjectOneDashboardMapController extends AbstractApiController
{
    public function __invoke(Request $request, ProjectOneMapDataService $mapData): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:view'), 403);
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        return $this->ok([
            'map_pins' => $mapData->buildPins(),
        ]);
    }
}
