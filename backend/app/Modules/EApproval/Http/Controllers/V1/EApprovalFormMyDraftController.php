<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalFormMyDraftController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalForm $form,
        EApprovalSubmissionService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $draft = $service->findRequestorDraft($request->user(), (string) $form->id);

        if ($draft === null) {
            return $this->ok(['draft' => null]);
        }

        return $this->ok(['draft' => $service->toDetailPayload($draft)]);
    }
}
