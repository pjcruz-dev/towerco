<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutBulkPhaseDatesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutBulkPhaseDatesGridController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutBulkPhaseDatesService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.rollout_id' => ['required', 'uuid', 'distinct'],
            'rows.*.phases' => ['required', 'array', 'max:30'],
            'rows.*.phases.*.phase_key' => ['required', 'string', 'max:64'],
            'rows.*.phases.*.actual_date' => ['required', 'date'],
            'mark_gate_passed' => ['sometimes', 'boolean'],
            'backfill' => ['required', 'accepted'],
        ]);

        $result = $service->bulkApplyGrid(
            $data['rows'],
            (bool) ($data['mark_gate_passed'] ?? true),
            $request->user(),
        );

        return $this->ok($result);
    }
}
