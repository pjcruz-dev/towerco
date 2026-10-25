<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\SubmissionWorkflowService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EApprovalSubmissionPreviewController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalSubmission $submission,
        SubmissionWorkflowService $workflow,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser && $actor->can('e_approval:approve'), 403);

        $data = $request->validate([
            'approver_id' => ['required', 'uuid'],
            'duty' => ['required', 'string', Rule::in(['review', 'approve'])],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $workflow->sendOptionalPreview(
            $submission,
            $actor,
            (string) $data['approver_id'],
            (string) $data['duty'],
            (string) $data['reason'],
        );

        return $this->ok(['sent' => true]);
    }
}
