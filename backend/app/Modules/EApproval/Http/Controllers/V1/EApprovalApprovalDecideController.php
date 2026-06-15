<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Services\ApprovalDecisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalApprovalDecideController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        EApprovalRequestApproval $approval,
        ApprovalDecisionService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('e_approval:approve'), 403);

        $data = $request->validate([
            'decision' => ['required', 'string', 'in:approved,rejected'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'signature' => ['nullable', 'string', 'max:500000'],
        ]);

        $updated = $service->decide(
            $approval,
            $data['decision'],
            $data['remarks'] ?? null,
            $data['signature'] ?? null,
            $request->user(),
        );

        return $this->ok(['approval' => $updated->toListRow()]);
    }
}
