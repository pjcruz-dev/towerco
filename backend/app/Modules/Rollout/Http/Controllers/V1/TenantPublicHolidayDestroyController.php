<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\TenantPublicHoliday;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantPublicHolidayDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TenantPublicHoliday $holiday,
        TenantPublicHolidayService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $service->delete($holiday);

        return $this->noContent();
    }
}
