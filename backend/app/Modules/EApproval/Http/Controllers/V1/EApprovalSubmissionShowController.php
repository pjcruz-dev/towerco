<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $service->assertCanView($submission, $request->user(), $canViewAll);

        return $this->ok($service->toDetailPayload($submission));
    }
}
