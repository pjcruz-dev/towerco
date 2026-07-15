<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutProgramUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutProgramService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'search_ring_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'region' => ['sometimes', 'nullable', 'string', 'max:64'],
            'territory' => ['sometimes', 'nullable', 'string', 'max:64'],
            'area' => ['sometimes', 'nullable', 'string', 'max:64'],
            'alliance_tag' => ['sometimes', 'nullable', 'string', 'max:128'],
            'mno_anchor_site_id' => ['sometimes', 'nullable', 'string', 'max:128'],
            'site_license_remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'energization_tempo_date' => ['sometimes', 'nullable', 'date'],
            'rfti_signed_tempo_date' => ['sometimes', 'nullable', 'date'],
            'endorsement_ref' => ['sometimes', 'nullable', 'string', 'max:128'],
            'endorsement_date' => ['sometimes', 'nullable', 'date'],
            'saq_owner_id' => ['sometimes', 'nullable', 'uuid'],
            'cme_pm_id' => ['sometimes', 'nullable', 'uuid'],
            'pmo_owner_id' => ['sometimes', 'nullable', 'uuid'],
            'project_id' => ['sometimes', 'nullable', 'uuid', 'exists:projects,id'],
        ]);

        $updated = $service->updateMetadata($rollout, $data);

        return $this->ok($presenter->detail($updated));
    }
}
