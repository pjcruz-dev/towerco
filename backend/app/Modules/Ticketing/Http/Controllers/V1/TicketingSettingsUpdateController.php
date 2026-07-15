<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingSettingsUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingSettingsService $settings,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:settings:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'it_support_email' => ['sometimes', 'string', 'max:2000'],
            'notify_it_on_create' => ['sometimes', 'boolean'],
            'notify_it_on_reopen' => ['sometimes', 'boolean'],
            'notify_requestor_on_resolve' => ['sometimes', 'boolean'],
            'notify_assignee_on_assign' => ['sometimes', 'boolean'],
            'sla_enabled' => ['sometimes', 'boolean'],
            'sla_response_minutes' => ['sometimes', 'integer', 'min:1'],
            'sla_escalation_minutes' => ['sometimes', 'integer', 'min:1'],
            'teams_webhook_url' => ['sometimes', 'string', 'max:2000'],
            'notify_teams_on_create' => ['sometimes', 'boolean'],
            'notify_teams_on_sla_reminder' => ['sometimes', 'boolean'],
            'notify_teams_on_sla_escalation' => ['sometimes', 'boolean'],
            'categories' => ['sometimes', 'array'],
            'categories.*' => ['string', 'max:64'],
            'apply_category_pack' => ['sometimes', 'string', 'max:64'],
        ]);

        $settings->update($data);

        return $this->ok($settings->snapshot());
    }
}
