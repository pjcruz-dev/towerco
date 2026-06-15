<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\TicketingTicket;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingCommentStoreController extends AbstractApiController
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

        $data = $request->validate([
            'body' => ['required', 'string', 'max:10000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $comment = $service->addComment(
            $ticket,
            $request->user(),
            $data['body'],
            (bool) ($data['is_internal'] ?? false),
        );

        return $this->created([
            'id' => (string) $comment->id,
            'body' => $comment->body,
            'is_internal' => $comment->is_internal,
            'created_at' => $comment->created_at?->toIso8601String(),
        ]);
    }
}
