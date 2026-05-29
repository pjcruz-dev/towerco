<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\SiteProfitabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteProfitabilityShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        SiteProfitabilityService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        /** @var TenantUser $user */
        $user = $request->user();

        return $this->ok($service->showForUser($rollout, $user));
    }
}
