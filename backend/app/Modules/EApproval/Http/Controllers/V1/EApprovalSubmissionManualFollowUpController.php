<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalSubmissionLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalSubmissionManualFollowUpController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalSubmissionLifecycleService $lifecycle,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:submissions:create'), 403);

        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->ok($lifecycle->manualFollowUp($submission, $data['note'] ?? null, $request->user()));
    }
}
