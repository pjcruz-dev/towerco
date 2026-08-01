<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionLifecycleService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionRevisionController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionLifecycleService $lifecycle,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:approve'), 403);

        $data = $request->validate([
            'remarks' => ['required', 'string', 'min:5'],
            'force_full_restart' => ['sometimes', 'boolean'],
        ]);

        $lifecycle->requestRevision(
            $submission,
            $data['remarks'],
            $request->user(),
            (bool) ($data['force_full_restart'] ?? false),
        );

        return $this->ok($submissions->toDetailPayload($submission->fresh()));
    }
}
