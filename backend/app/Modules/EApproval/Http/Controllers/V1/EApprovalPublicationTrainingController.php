<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalPublicationTrainingService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicationTrainingController extends AbstractApiController
{
    public function invite(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalPublicationTrainingService $training,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);
        $canManage = (bool) $request->user()?->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canManage);
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:50'],
            'user_ids.*' => ['uuid'],
        ]);

        return $this->ok($training->invite($submission, $request->user(), $data['user_ids'], $canManage) ?? []);
    }

    public function complete(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalPublicationTrainingService $training,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);
        $canManage = (bool) $request->user()?->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canManage);
        $data = $request->validate([
            'invite_id' => ['nullable', 'uuid'],
        ]);

        return $this->ok($training->complete($submission, $request->user(), $canManage, $data['invite_id'] ?? null) ?? []);
    }

    public function remove(
        Request $request,
        EApprovalSubmission $submission,
        string $invite,
        EApprovalPublicationTrainingService $training,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:view'), 403);
        $canManage = (bool) $request->user()?->can('e_approval:forms:manage');
        $submissions->assertCanView($submission, $request->user(), $canManage);

        return $this->ok($training->remove($submission, $request->user(), $invite, $canManage) ?? []);
    }
}
