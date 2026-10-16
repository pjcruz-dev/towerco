<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketingFieldDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $field,
        TicketingSchemaService $schema,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:fields:manage'), 403);
        $planFeatures->assertModuleEnabled();
        $schema->deleteField(DynField::query()->findOrFail($field));

        return $this->ok(['deleted' => true]);
    }
}
