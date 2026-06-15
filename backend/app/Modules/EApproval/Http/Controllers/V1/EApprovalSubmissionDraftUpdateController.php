<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionDraftUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $data = $request->validate([
            'values' => ['required', 'array'],
            'parent_submission_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $updateParentLink = array_key_exists('parent_submission_id', $data);
        $parentSubmissionId = $updateParentLink ? ($data['parent_submission_id'] ?? null) : null;

        $updated = $service->updateDraft(
            $submission,
            $data['values'],
            $request->user(),
            $parentSubmissionId,
            $updateParentLink,
        );

        return $this->ok($service->toDetailPayload($updated));
    }
}
