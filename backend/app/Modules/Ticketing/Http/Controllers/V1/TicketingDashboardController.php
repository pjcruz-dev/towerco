<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Ticketing\Services\TicketingDashboardService;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingDashboardController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingDashboardService $service,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:view'), 403);
        $planFeatures->assertModuleEnabled();

        return $this->ok($service->build($request->user()));
    }
}
