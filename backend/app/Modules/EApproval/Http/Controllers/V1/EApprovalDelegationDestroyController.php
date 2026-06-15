<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Models\EApprovalDelegation;
use App\Modules\EApproval\Services\EApprovalDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalDelegationDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalDelegation $delegation, EApprovalDelegationService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:approve'), 403);

        $service->revoke($delegation, $request->user());

        return $this->ok(['ok' => true]);
    }
}
