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
            'attachment_id' => ['nullable', 'uuid'],
            'pin_page' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'pin_x' => ['nullable', 'numeric', 'between:0,1'],
            'pin_y' => ['nullable', 'numeric', 'between:0,1'],
            'pin_w' => ['nullable', 'numeric', 'between:0,1'],
            'pin_h' => ['nullable', 'numeric', 'between:0,1'],
            'mention_user_ids' => ['nullable', 'array', 'max:20'],
            'mention_user_ids.*' => ['uuid'],
        ]);

        $comment = $comments->add(
            $submission,
            $data['message'],
            $request->user(),
            $data['parent_id'] ?? null,
            true,
            $data['attachment_id'] ?? null,
            isset($data['pin_page']) ? (int) $data['pin_page'] : null,
            isset($data['pin_x']) ? (float) $data['pin_x'] : null,
            isset($data['pin_y']) ? (float) $data['pin_y'] : null,
            isset($data['pin_w']) ? (float) $data['pin_w'] : null,
            isset($data['pin_h']) ? (float) $data['pin_h'] : null,
            array_values($data['mention_user_ids'] ?? []),
        );

        return $this->created(['id' => (string) $comment->id]);
    }
}
