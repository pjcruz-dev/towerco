<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Models\TicketingAttachment;
use App\Modules\Ticketing\Services\TicketingFileStorageService;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketingAttachmentDownloadController
{
    public function __invoke(
        Request $request,
        TicketingAttachment $attachment,
        TicketingFileStorageService $files,
        TicketingTicketService $tickets,
        TicketingPlanFeaturesService $planFeatures,
    ): StreamedResponse {
        abort_unless($request->user()?->can('ticketing:view'), 403);
        $planFeatures->assertModuleEnabled();

        $attachment->load('ticket');
        $tickets->assertCanView($attachment->ticket, $request->user());

        return $files->download($attachment);
    }
}
