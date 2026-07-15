<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalApprovalPolicyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalApprovalPolicyShowController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalApprovalPolicyService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:settings:manage'), 403);

        return $this->ok($service->snapshotForAdmin());
    }
}
