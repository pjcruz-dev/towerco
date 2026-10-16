<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalFinanceStageService;
use App\Modules\EApproval\Services\EApprovalSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalCashAdvanceConfirmReturnController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalFinanceStageService $finance,
        EApprovalSubmissionService $submissions,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:approve'), 403);

        $data = $request->validate([
            'settlement_reference' => ['required', 'string', 'max:120'],
        ]);

        $updated = $finance->confirmReturn($submission, $request->user(), $data['settlement_reference']);

        return $this->ok($submissions->toDetailPayload($updated, $request->user()));
    }
}
