<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutBulkPhaseDatesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutBulkPhaseDatesController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutBulkPhaseDatesService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'rollout_ids' => ['required', 'array', 'min:1', 'max:100'],
            'rollout_ids.*' => ['required', 'uuid', 'distinct'],
            'phases' => ['required', 'array', 'min:1', 'max:30'],
            'phases.*.phase_key' => ['required', 'string', 'max:64'],
            'phases.*.actual_date' => ['required', 'date'],
            'mark_gate_passed' => ['sometimes', 'boolean'],
            'backfill' => ['required', 'accepted'],
        ]);

        $result = $service->bulkApply(
            $data['rollout_ids'],
            $data['phases'],
            (bool) ($data['mark_gate_passed'] ?? true),
            $request->user(),
        );

        return $this->ok($result);
    }
}
