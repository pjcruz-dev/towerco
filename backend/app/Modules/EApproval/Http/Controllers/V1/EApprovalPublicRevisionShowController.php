<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalPublicSubmissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalPublicRevisionShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        EApprovalPublicSubmissionService $submissions,
    ): JsonResponse {
        $data = $request->validate([
            'resubmit_token' => ['required', 'string', 'max:128'],
        ]);

        return $this->ok($submissions->showForRevise($submission, $data['resubmit_token']));
    }
}
