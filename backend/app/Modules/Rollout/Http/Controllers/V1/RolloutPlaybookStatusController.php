<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutPlaybookStatusController extends AbstractApiController
{
    public function __invoke(RolloutProgramService $service): JsonResponse
    {
        abort_unless(auth()->user()?->can('project_one:view'), 403);

        return $this->ok($service->playbookStatus());
    }
}
