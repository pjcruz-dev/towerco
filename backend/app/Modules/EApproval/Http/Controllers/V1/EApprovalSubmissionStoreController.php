<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use App\Modules\EApproval\Support\EApprovalRoleFieldAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmissionService $service,
        EApprovalRoleFieldAccess $roleAccess,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);
        abort_unless($roleAccess->allows($request->user(), 'create'), 403);

        $data = $request->validate([
            'form_id' => ['required', 'uuid'],
            'values' => ['required', 'array'],
            'as_draft' => ['sometimes', 'boolean'],
            'parent_submission_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $updateParentLink = array_key_exists('parent_submission_id', $data);
        $parentSubmissionId = $updateParentLink ? ($data['parent_submission_id'] ?? null) : null;

        $submission = ! empty($data['as_draft'])
            ? $service->createDraft(
                $data['form_id'],
                $data['values'],
                $request->user(),
                $parentSubmissionId,
                $updateParentLink,
            )
            : $service->create($data['form_id'], $data['values'], $request->user(), $parentSubmissionId);

        return $this->created($roleAccess->redactDetail($request->user(), $service->toDetailPayload($submission, $request->user())));
    }
}
