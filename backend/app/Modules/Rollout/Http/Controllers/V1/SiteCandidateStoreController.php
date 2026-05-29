<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutMediaAttachmentService;
use App\Modules\Rollout\Services\SiteCandidateService;
use App\Modules\Rollout\Support\RolloutCoordinateRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCandidateStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        SiteCandidateService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:saq:manage'), 403);

        $data = $request->validate(array_merge([
            'client_draft_id' => ['sometimes', 'nullable', 'uuid'],
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lessor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lessor_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proposed_lease_rate_php' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'row_notes' => ['sometimes', 'nullable', 'string'],
            'power_notes' => ['sometimes', 'nullable', 'string'],
            'hazard_notes' => ['sometimes', 'nullable', 'string'],
        ], RolloutCoordinateRules::inputRules(), RolloutMediaAttachmentService::photoLinksRules(), RolloutMediaAttachmentService::leasePackageRules()));

        $data = RolloutCoordinateRules::applyToInput($data);

        $result = $service->create($rollout, $data);
        $candidate = $result['record'];

        $payload = [
            'id' => $candidate->id,
            'candidate_number' => $candidate->candidate_number,
            'status' => $candidate->status,
        ];

        return $result['created']
            ? $this->created($payload)
            : $this->ok($payload);
    }
}
