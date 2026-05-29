<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutGateApprovalRequest;
use App\Modules\Rollout\Services\RolloutGateApprovalService;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGateApprovalDecideController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutGateApprovalRequest $gateApproval,
        RolloutGateApprovalService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approve,reject'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $updated = $data['decision'] === 'approve'
            ? $service->approveStep($gateApproval, $data['notes'] ?? null, $request->user())
            : $service->reject($gateApproval, $data['notes'] ?? null, $request->user());

        $updated->load('rolloutProgram');

        return $this->ok([
            'approval' => $service->presentRequest($updated, $request->user()),
            'rollout' => $updated->rolloutProgram
                ? $presenter->detail($updated->rolloutProgram, $request->user())
                : null,
        ]);
    }
}
