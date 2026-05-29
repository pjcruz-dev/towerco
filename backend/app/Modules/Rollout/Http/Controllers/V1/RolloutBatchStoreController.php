<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutBatchStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutProgramService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'mno' => ['required', 'string', 'in:globe,smart,dito'],
            'project_type' => ['required', 'string', 'in:bts,rtb,colocation,colo'],
            'batch_label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'endorsement_ref' => ['sometimes', 'nullable', 'string', 'max:128'],
            'endorsement_date' => ['sometimes', 'date'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'territory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'rollout_ref' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sites' => ['required', 'array', 'min:1', 'max:25'],
            'sites.*.search_ring_name' => ['required', 'string', 'max:255'],
            'sites.*.region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sites.*.territory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'sites.*.rollout_ref' => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        $sites = $data['sites'];
        unset($data['sites']);

        $result = $service->createBatch($data, $sites);

        return $this->created([
            'parent' => [
                'id' => $result['parent']->id,
                'rollout_ref' => $result['parent']->rollout_ref,
                'status' => $result['parent']->status,
                'batch_label' => $result['parent']->search_ring_name,
            ],
            'children' => collect($result['children'])->map(static fn ($child) => [
                'id' => $child->id,
                'rollout_ref' => $child->rollout_ref,
                'search_ring_name' => $child->search_ring_name,
                'status' => $child->status,
            ])->values()->all(),
        ]);
    }
}
