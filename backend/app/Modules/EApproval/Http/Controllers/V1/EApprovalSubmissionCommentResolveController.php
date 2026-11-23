<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalComment;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalCommentService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionCommentResolveController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalComment $comment,
        EApprovalCommentService $comments,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);

        $canViewAll = $request->user()->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canViewAll);

        $data = $request->validate([
            'resolved' => ['required', 'boolean'],
        ]);

        $saved = $comments->resolve($submission, $comment, $request->user(), (bool) $data['resolved']);
        $saved->load('resolvedBy:id,name');

        return $this->ok([
            'id' => (string) $saved->id,
            'resolved_at' => $saved->resolved_at?->toIso8601String(),
            'resolved_by_name' => $saved->resolvedBy?->name,
        ]);
    }
}
