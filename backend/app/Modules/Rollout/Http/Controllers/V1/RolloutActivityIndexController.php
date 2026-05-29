<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutActivityIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutActivityService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $limit = (int) $request->query('limit', 40);

        return $this->ok($service->listForRollout($rollout, $limit));
    }
}
