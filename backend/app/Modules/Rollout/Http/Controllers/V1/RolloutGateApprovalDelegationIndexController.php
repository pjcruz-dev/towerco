<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Services\RolloutGateApprovalDelegationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGateApprovalDelegationIndexController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGateApprovalDelegationService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        /** @var TenantUser $user */
        $user = $request->user();

        return $this->ok([
            'delegations' => collect($service->listForUser($user))
                ->map(fn ($row) => $service->present($row))
                ->values()
                ->all(),
        ]);
    }
}
