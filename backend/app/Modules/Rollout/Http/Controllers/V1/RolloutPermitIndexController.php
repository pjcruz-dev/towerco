<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutPermitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RolloutPermitIndexController extends AbstractApiController
{
    public function __invoke(RolloutProgram $rollout, RolloutPermitService $permits): JsonResponse
    {
        abort_unless(request()->user()?->can('project_one:rollout:view'), 403);

        return $this->ok($permits->listForProgram($rollout));
    }
}
