<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalCommentService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionCommentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalCommentService $comments,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'parent_id' => ['nullable', 'uuid'],
        ]);

        $comment = $comments->add($submission, $data['message'], $request->user(), $data['parent_id'] ?? null);

        return $this->created(['id' => (string) $comment->id]);
    }
}
