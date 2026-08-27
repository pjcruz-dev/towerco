<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmissionShareLink;
use App\Modules\EApproval\Services\EApprovalSubmissionShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionShareLinkRevokeController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmissionShareLink $shareLink,
        EApprovalSubmissionShareLinkService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $shareLink->loadMissing('submission');
        abort_unless($shareLink->submission !== null, 404);
        $service->assertCanManageShares($shareLink->submission, $request->user());

        $service->revoke($shareLink, $request->user());

        return $this->ok(['revoked' => true]);
    }
}
