<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketingSchemaShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingSchemaService $schema,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless(
            $request->user()?->can('ticketing:view')
            || $request->user()?->can('ticketing:tickets:create')
            || $request->user()?->can('ticketing:fields:manage'),
            403,
        );
        $planFeatures->assertModuleEnabled();

        return $this->ok($schema->show());
    }
}
