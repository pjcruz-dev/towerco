<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\TicketingTicket;
use App\Modules\Ticketing\Services\TicketingFileStorageService;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingAttachmentStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingTicket $ticket,
        TicketingTicketService $tickets,
        TicketingFileStorageService $files,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:tickets:create'), 403);
        $planFeatures->assertCanUploadAttachment();
        $tickets->assertCanView($ticket, $request->user());

        $planFeatures->assertAttachmentLimitNotExceeded($ticket->attachments()->count());

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $attachment = $files->store($ticket, $data['file'], $request->user());

        return $this->created([
            'id' => (string) $attachment->id,
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
        ]);
    }
}
