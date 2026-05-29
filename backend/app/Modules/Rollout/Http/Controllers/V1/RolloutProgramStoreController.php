<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutProgramStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutProgramService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'mno' => ['required', 'string', 'in:globe,smart,dito'],
            'project_type' => ['required', 'string', 'in:bts,rtb,colocation,colo'],
            'endorsement_ref' => ['sometimes', 'nullable', 'string', 'max:128'],
            'endorsement_date' => ['sometimes', 'date'],
            'search_ring_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'territory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'rollout_ref' => ['sometimes', 'nullable', 'string', 'max:64'],
            'project_id' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
        ]);

        $program = $service->create($data);

        return $this->created([
            'id' => $program->id,
            'rollout_ref' => $program->rollout_ref,
            'sla_working_days' => $program->sla_working_days,
            'status' => $program->status,
        ]);
    }
}
