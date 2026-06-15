<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Services\EApprovalApprovalRerouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalApprovalRerouteController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalRequestApproval $approval,
        EApprovalApprovalRerouteService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:forms:manage'), 403);

        $data = $request->validate([
            'new_approver_id' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $updated = $service->reroute($approval, $data['new_approver_id'], $data['reason'], $request->user());

        return $this->ok([
            'id' => (string) $updated->id,
            'approver_id' => (string) $updated->approver_id,
            'submission_id' => (string) $updated->submission_id,
        ]);
    }
}
