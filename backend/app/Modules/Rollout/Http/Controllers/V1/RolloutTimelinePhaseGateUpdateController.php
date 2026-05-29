<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutTimelinePhaseGateUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutTimelinePhase $phase,
        RolloutProgramService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'gate_status' => ['required', 'string', 'in:pending,passed,failed,waived'],
        ]);

        $service->updatePhaseGateStatus($phase, $data['gate_status']);
        $phase->load('rolloutProgram');

        return $this->ok($presenter->detail($phase->rolloutProgram));
    }
}
