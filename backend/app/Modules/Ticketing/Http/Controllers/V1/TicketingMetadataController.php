<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Models\TicketingTicket;
use App\Modules\Ticketing\Support\TicketingCategoryCatalog;
use App\Modules\Ticketing\Support\TicketingSourceCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingMetadataController extends AbstractApiController
{
    public function __invoke(Request $request, TicketingSourceCatalog $sources, TicketingCategoryCatalog $categories): JsonResponse
    {
        abort_unless($request->user()?->can('ticketing:view'), 403);

        return $this->ok([
            'statuses' => [
                TicketingTicket::STATUS_OPEN,
                TicketingTicket::STATUS_IN_PROGRESS,
                TicketingTicket::STATUS_RESOLVED,
                TicketingTicket::STATUS_CLOSED,
            ],
            'priorities' => [
                TicketingTicket::PRIORITY_LOW,
                TicketingTicket::PRIORITY_NORMAL,
                TicketingTicket::PRIORITY_HIGH,
                TicketingTicket::PRIORITY_URGENT,
            ],
            'categories' => $categories->resolve(),
            'source_modules' => collect($sources->modules())->map(fn (string $module) => [
                'id' => $module,
                'label' => $sources->labels()[$module] ?? $module,
            ])->values()->all(),
        ]);
    }
}
