<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutTimelinePhase;
use App\Modules\Rollout\Services\RolloutGateApprovalService;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGateApprovalSubmitController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutTimelinePhase $phase,
        RolloutGateApprovalService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'request_notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $approval = $service->submit($phase, $data['request_notes'] ?? null, $request->user());
        $phase->load('rolloutProgram');

        return $this->ok([
            'approval' => $service->presentRequest($approval, $request->user()),
            'rollout' => $phase->rolloutProgram
                ? $presenter->detail($phase->rolloutProgram, $request->user())
                : null,
        ]);
    }
}
