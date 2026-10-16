<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketingFieldStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        TicketingSchemaService $schema,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:fields:manage'), 403);
        $planFeatures->assertModuleEnabled();
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'name' => ['nullable', 'string', 'max:128'],
            'type' => ['nullable', 'string', 'max:64'],
            'is_required' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'field_order' => ['sometimes', 'integer'],
            'form_group_id' => ['nullable', 'uuid'],
            'options_json' => ['nullable', 'array'],
        ]);

        return $this->created($schema->presentField($schema->createField($data, $actor)));
    }
}
