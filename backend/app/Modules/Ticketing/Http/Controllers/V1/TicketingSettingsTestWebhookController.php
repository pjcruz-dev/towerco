<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSettingsTestWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingSettingsTestWebhookController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingSettingsTestWebhookService $service,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $service->send();

        return $this->ok([
            'message' => __('Test webhook sent. Check your Teams channel for the TowerOS Ticketing test message.'),
        ]);
    }
}
