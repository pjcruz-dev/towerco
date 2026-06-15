<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalCashAdvanceService;
use App\Modules\EApproval\Services\EApprovalSubmissionParentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalCashAdvanceOpenController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalCashAdvanceService $service,
        EApprovalSubmissionParentLinkService $parentLinks,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('e_approval:submissions:create')
            || $request->user()?->can('e_approval:submissions:view'),
            403,
        );

        $items = $service->openForUser($request->user());

        $forFormId = trim((string) $request->query('for_form_id', ''));
        if ($forFormId !== '') {
            $childForm = EApprovalForm::query()->with('fields')->find($forFormId);
            if ($childForm !== null) {
                $items = $parentLinks->attachPrefillToOpenParentItems($items, $childForm);
            }
        }

        return $this->ok(['items' => $items]);
    }
}
