<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutMapService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutMapController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutMapService $mapService,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        return $this->ok($mapService->geoJson($rollout));
    }
}
