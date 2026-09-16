<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\TicketingAttachment;
use App\Modules\Ticketing\Services\TicketingFileStorageService;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingAttachmentDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingAttachment $attachment,
        TicketingFileStorageService $files,
        TicketingTicketService $tickets,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:tickets:create') || $request->user()?->can('ticketing:tickets:manage'), 403);
        $planFeatures->assertModuleEnabled();

        $attachment->load('ticket');
        $ticket = $attachment->ticket;
        abort_if($ticket === null, 404);

        $user = $request->user();
        $tickets->assertCanView($ticket, $user);

        $isUploader = (string) ($attachment->uploaded_by_id ?? '') === (string) $user->id;
        $isRequester = (string) ($ticket->requester_id ?? '') === (string) $user->id;
        $canManage = $user->can('ticketing:tickets:manage');
        abort_unless($canManage || $isUploader || $isRequester, 403);

        $files->deleteAttachment($attachment);

        return $this->ok([
            'id' => (string) $attachment->id,
            'deleted' => true,
        ]);
    }
}
