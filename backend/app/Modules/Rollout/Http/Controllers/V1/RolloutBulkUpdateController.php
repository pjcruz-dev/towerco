<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutBulkUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutBulkUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutBulkUpdateService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'rollout_ids' => ['required', 'array', 'min:1', 'max:100'],
            'rollout_ids.*' => ['required', 'uuid', 'distinct'],
            'updates' => ['required', 'array', 'min:1'],
            'updates.search_ring_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'updates.region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'updates.territory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'updates.area' => ['sometimes', 'nullable', 'string', 'max:64'],
            'updates.alliance_tag' => ['sometimes', 'nullable', 'string', 'max:128'],
            'updates.mno_anchor_site_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'updates.site_license_remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'updates.energization_tempo_date' => ['sometimes', 'nullable', 'date'],
            'updates.rfti_signed_tempo_date' => ['sometimes', 'nullable', 'date'],
            'updates.endorsement_ref' => ['sometimes', 'nullable', 'string', 'max:128'],
            'updates.endorsement_date' => ['sometimes', 'nullable', 'date'],
            'updates.saq_owner_id' => ['sometimes', 'nullable', 'uuid'],
            'updates.cme_pm_id' => ['sometimes', 'nullable', 'uuid'],
            'updates.pmo_owner_id' => ['sometimes', 'nullable', 'uuid'],
            'updates.project_id' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
        ]);

        $result = $service->bulkUpdate(
            $data['rollout_ids'],
            $data['updates'],
            $request->user(),
        );

        return $this->ok($result);
    }
}
