<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormShowController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalForm $form): JsonResponse
    {
        $user = $request->user();
        $canView = $user?->can('e_approval:view') || $user?->can('e_approval:forms:manage');
        $canSubmit = $user?->can('e_approval:submissions:create') === true
            && $form->status === 'published'
            && $form->accepts_new_submissions !== false;

        abort_unless($canView || $canSubmit, 403);

        if ($form->status === 'draft' && ! $user?->can('e_approval:forms:manage')) {
            abort(404);
        }

        $form->load(['fields', 'workflowTemplate.steps']);
        $form->loadCount('submissions');

        $includeRevisionSnapshots = $user?->can('e_approval:forms:manage') === true;

        return $this->ok($form->toDetailPayload($includeRevisionSnapshots));
    }
}
