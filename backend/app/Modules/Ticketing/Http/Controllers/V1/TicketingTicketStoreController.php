<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingNotificationDispatcher;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingTicketStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingTicketService $service,
        TicketingPlanFeaturesService $planFeatures,
        TicketingNotificationDispatcher $notifications,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:tickets:create'), 403);
        $planFeatures->assertModuleEnabled();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:20000'],
            'category' => ['nullable', 'string', 'max:64'],
            'source_module' => ['nullable', 'string', 'max:64'],
            'source_reference_type' => ['nullable', 'string', 'max:128'],
            'source_reference_id' => ['nullable', 'string', 'max:36'],
            'source_label' => ['nullable', 'string', 'max:255'],
            'assignee_id' => ['nullable', 'uuid', 'exists:users,id'],
            'links' => ['nullable', 'array'],
            'links.*.link_module' => ['required_with:links', 'string', 'max:64'],
            'links.*.link_type' => ['required_with:links', 'string', 'max:128'],
            'links.*.link_id' => ['required_with:links', 'string', 'max:36'],
            'links.*.link_label' => ['nullable', 'string', 'max:255'],
        ]);

        if (! $request->user()->can('ticketing:tickets:manage')) {
            unset($data['assignee_id']);
        }

        $ticket = $service->create($request->user(), $data);
        $notifications->dispatchCreated($ticket, $request->user());

        $ticket->loadMissing('assignee:id,name,email');
        if ($ticket->assignee instanceof TenantUser) {
            $notifications->dispatchAssigned($ticket, $request->user(), $ticket->assignee);
        }

        return $this->created($service->asDetail($ticket, $request->user()));
    }
}
