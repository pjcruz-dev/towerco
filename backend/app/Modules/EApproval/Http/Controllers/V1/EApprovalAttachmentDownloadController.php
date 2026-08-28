<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EApprovalAttachmentDownloadController
{
    public function __invoke(
        Request $request,
        EApprovalAttachment $attachment,
        EApprovalFileStorageService $files,
        EApprovalSubmissionService $submissions,
    ): StreamedResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $attachment->load('submission');
        $submission = $attachment->submission;
        abort_if($submission === null, 404);

        $user = $request->user();
        $canViewAll = $user->can('e_approval:forms:manage')
            || $user->can('e_approval:audit:view');
        $submissions->assertCanView($submission, $user, $canViewAll);

        return $files->download($attachment);
    }
}
