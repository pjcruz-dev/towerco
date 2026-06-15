<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalFileStorageService;
use App\Modules\EApproval\Services\EApprovalPlanFeaturesService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionAttachmentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalFileStorageService $files,
        EApprovalSubmissionService $submissions,
        EApprovalPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $planFeatures->assertCanUploadAttachment();

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'field_name' => ['nullable', 'string', 'max:255'],
        ]);

        $attachment = $files->store($submission, $data['file'], $data['field_name'] ?? null);

        return $this->created([
            'id' => (string) $attachment->id,
            'file_name' => $attachment->file_name,
            'field_name' => $attachment->field_name,
        ]);
    }
}
