<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\TicketingTicket;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingTicketShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingTicket $ticket,
        TicketingTicketService $service,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:view'), 403);
        $planFeatures->assertModuleEnabled();
        $service->assertCanView($ticket, $request->user());

        return $this->ok($service->asDetail($ticket, $request->user()));
    }
}
