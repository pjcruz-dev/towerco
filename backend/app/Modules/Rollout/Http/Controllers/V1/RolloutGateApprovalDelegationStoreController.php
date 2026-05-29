<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Services\RolloutGateApprovalDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGateApprovalDelegationStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGateApprovalDelegationService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user?->can('project_one:rollout:gate:approve')
            || $user?->can('project_one:rollout:manage')
            || $user?->can('project_one:saq:manage')
            || $user?->can('project_one:cme:manage'),
            403,
        );

        $data = $request->validate([
            'delegate_id' => ['required', 'uuid'],
            'role_key' => ['nullable', 'string', 'max:64'],
            'valid_from' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        /** @var TenantUser $user */
        $user = $request->user();
        $delegation = $service->create($user, $data);

        return $this->ok($service->present($delegation), 201);
    }
}
