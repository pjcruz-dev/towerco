<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingSettingsShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingSettingsService $settings,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        return $this->ok($settings->snapshot());
    }
}
