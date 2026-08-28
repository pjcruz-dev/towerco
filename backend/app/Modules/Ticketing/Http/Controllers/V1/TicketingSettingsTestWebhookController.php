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

        $data = $request->validate([
            'teams_webhook_url' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $service->send(isset($data['teams_webhook_url']) ? (string) $data['teams_webhook_url'] : null);

        return $this->ok([
            'message' => __('Test webhook sent. Check your Teams channel for the TowerOS Ticketing test message.'),
        ]);
    }
}
