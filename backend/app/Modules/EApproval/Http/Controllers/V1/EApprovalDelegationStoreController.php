<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalDelegationStoreController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalDelegationService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:approve'), 403);

        $data = $request->validate([
            'delegate_id' => ['required', 'uuid'],
            'valid_from' => ['sometimes', 'date'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $delegation = $service->create($request->user(), $data);

        return $this->ok($service->present($delegation), 201);
    }
}
