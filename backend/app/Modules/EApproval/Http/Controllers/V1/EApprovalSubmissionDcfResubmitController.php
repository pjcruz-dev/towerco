<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionLifecycleService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionDcfResubmitController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionLifecycleService $lifecycle,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $data = $request->validate([
            'values' => ['required', 'array'],
        ]);

        $updated = $lifecycle->dcfResubmit($submission, $data['values'], $request->user());

        return $this->ok($submissions->toDetailPayload($updated));
    }
}
