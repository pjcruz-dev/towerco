<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionShareLinkIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionShareLinkService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);
        $service->assertCanManageShares($submission, $request->user());

        return $this->ok($service->listForSubmission($submission));
    }
}
