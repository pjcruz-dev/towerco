<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSettingsTestEmailService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingSettingsTestEmailController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingSettingsTestEmailService $service,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        /** @var TenantUser $user */
        $user = $request->user();

        $result = $service->sendToUser($user);

        return $this->ok([
            'message' => __('Test email sent. Check your inbox (and spam) for the TowerOS Ticketing test message.'),
            'sent_to' => $result['sent_to'],
            'mailer' => $result['mailer'],
        ]);
    }
}
