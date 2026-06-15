<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\TicketingTicket;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingNotificationDispatcher;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TicketingTicketUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingTicket $ticket,
        TicketingTicketService $service,
        TicketingPlanFeaturesService $planFeatures,
        TicketingNotificationDispatcher $notifications,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:view'), 403);
        $planFeatures->assertModuleEnabled();
        $service->assertCanView($ticket, $request->user());

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'priority' => ['sometimes', 'string', Rule::in([
                TicketingTicket::PRIORITY_LOW,
                TicketingTicket::PRIORITY_NORMAL,
                TicketingTicket::PRIORITY_HIGH,
                TicketingTicket::PRIORITY_URGENT,
            ])],
            'category' => ['nullable', 'string', 'max:64'],
            'status' => ['sometimes', 'string', Rule::in([
                TicketingTicket::STATUS_OPEN,
                TicketingTicket::STATUS_IN_PROGRESS,
                TicketingTicket::STATUS_RESOLVED,
                TicketingTicket::STATUS_CLOSED,
            ])],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'resolution_comment' => ['nullable', 'string', 'max:10000'],
        ]);

        $result = $service->update($ticket, $request->user(), $data);
        $updated = $result['ticket'];

        if ($result['lifecycle_event'] === 'resolved') {
            $notifications->dispatchResolved(
                $updated,
                $request->user(),
                (string) ($result['resolution_comment'] ?? ''),
            );
        } elseif ($result['lifecycle_event'] === 'reopened') {
            $notifications->dispatchReopened($updated, $request->user());
        }

        if ($result['assignee_changed'] && $result['new_assignee'] instanceof TenantUser) {
            $notifications->dispatchAssigned($updated, $request->user(), $result['new_assignee']);
        }

        return $this->ok($service->asDetail($updated, $request->user()));
    }
}
