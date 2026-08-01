<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Services\EApprovalSubmissionWorkflowPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionWorkflowPreviewController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionService $submissions,
        EApprovalSubmissionWorkflowPreviewService $preview,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $submissions->assertCanView($submission, $request->user(), true);

        return $this->ok($preview->preview($submission));
    }
}
