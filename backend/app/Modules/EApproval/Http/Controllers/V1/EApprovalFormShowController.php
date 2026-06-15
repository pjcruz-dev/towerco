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
        abort_unless($request->user()?->can('e_approval:view'), 403);

        if ($form->status === 'draft' && ! $request->user()?->can('e_approval:forms:manage')) {
            abort(404);
        }

        $form->load(['fields', 'workflowTemplate.steps']);
        $form->loadCount('submissions');

        return $this->ok($form->toDetailPayload());
    }
}
