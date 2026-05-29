<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\SiteProfitabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteProfitabilityUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        SiteProfitabilityService $service,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('project_one:finance:edit') || $request->user()?->can('tenant:manage'),
            403,
        );

        $data = $request->validate([
            'baseline' => ['sometimes', 'array'],
            'actual' => ['sometimes', 'array'],
            'vo_cost_cumulative' => ['sometimes', 'numeric', 'min:0'],
            'ld_accrued_php' => ['sometimes', 'numeric', 'min:0'],
            'variance_category' => ['sometimes', 'nullable', 'string', 'max:64'],
            'profitability_status' => ['sometimes', 'string', 'in:on_track,watch,underperforming,at_loss'],
            'anchor_tenant_lease_fee_php' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ]);

        /** @var TenantUser $user */
        $user = $request->user();
        $service->update($rollout, $user, $data);

        return $this->ok($service->showForUser($rollout->fresh(['profitability']), $user));
    }
}
