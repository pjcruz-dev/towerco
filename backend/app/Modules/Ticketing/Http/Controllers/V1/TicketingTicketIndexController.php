<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Concerns\ValidatesTenantListQuery;
use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketingTicketIndexController extends AbstractApiController
{
    use ValidatesTenantListQuery;

    public function __invoke(
        Request $request,
        TicketingTicketService $service,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:view'), 403);
        $planFeatures->assertModuleEnabled();

        $listQuery = $this->validatedTenantListQuery($request);
        $paginator = $service->paginate($request->user(), [
            ...$listQuery,
            'status' => $request->query('status'),
            'priority' => $request->query('priority'),
            'assignee_id' => $request->query('assignee_id'),
            'source_module' => $request->query('source_module'),
            'source_reference_id' => $request->query('source_reference_id'),
            'mine' => $request->boolean('mine'),
        ]);

        $data = collect($paginator->items())->map(
            fn ($ticket) => $service->asListRow($ticket),
        )->all();

        return $this->okWithMeta($data, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
