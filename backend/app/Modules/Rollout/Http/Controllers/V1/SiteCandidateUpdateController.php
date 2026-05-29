<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Services\RolloutMediaAttachmentService;
use App\Modules\Rollout\Services\SiteCandidateService;
use App\Modules\Rollout\Support\RolloutCoordinateRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCandidateUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        SiteCandidate $candidate,
        SiteCandidateService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:saq:manage'), 403);

        $data = $request->validate(array_merge([
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lessor_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'lessor_contact' => ['sometimes', 'nullable', 'string', 'max:255'],
            'proposed_lease_rate_php' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'row_notes' => ['sometimes', 'nullable', 'string'],
            'power_notes' => ['sometimes', 'nullable', 'string'],
            'hazard_notes' => ['sometimes', 'nullable', 'string'],
            'status' => ['sometimes', 'string', 'in:scouted,shortlisted'],
        ], RolloutCoordinateRules::inputRules(), RolloutMediaAttachmentService::photoLinksRules(), RolloutMediaAttachmentService::leasePackageRules()));

        $data = RolloutCoordinateRules::applyToInput($data);

        $updated = $service->update($candidate, $data);

        return $this->ok(['id' => $updated->id, 'status' => $updated->status]);
    }
}
