<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalDelegationIndexController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalDelegationService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        $items = array_map(static fn ($d) => $service->present($d), $service->listForUser($request->user()));

        return $this->ok(['data' => $items]);
    }
}
