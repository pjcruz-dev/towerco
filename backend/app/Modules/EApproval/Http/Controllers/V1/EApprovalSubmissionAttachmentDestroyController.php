<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionAttachmentDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalAttachment $attachment,
        EApprovalFileStorageService $files,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $attachment->load('submission');
        $submission = $attachment->submission;
        abort_if($submission === null, 404);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);
        $submissions->assertCanEditAttachments($submission, $request->user());

        $files->deleteAttachment($attachment);

        return $this->ok([
            'id' => (string) $attachment->id,
            'deleted' => true,
        ]);
    }
}
