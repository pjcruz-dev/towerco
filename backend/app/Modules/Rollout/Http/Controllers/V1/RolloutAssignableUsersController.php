<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserAssignableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutAssignableUsersController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserAssignableService $service): JsonResponse
    {
        abort_unless(
            $request->user()?->can('project_one:rollout:manage')
            || $request->user()?->can('project_one:rollout:view'),
            403,
        );

        return $this->ok($service->listForRolloutAssignment());
    }
}
