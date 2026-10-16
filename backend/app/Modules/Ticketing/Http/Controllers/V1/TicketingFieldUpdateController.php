<?php

declare(strict_types=1);

namespace App\Modules\Ticketing\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Ticketing\Services\TicketingPlanFeaturesService;
use App\Modules\Ticketing\Services\TicketingSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TicketingFieldUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $field,
        TicketingSchemaService $schema,
        TicketingPlanFeaturesService $planFeatures,
    ): JsonResponse {
        abort_unless($request->user()?->can('ticketing:fields:manage'), 403);
        $planFeatures->assertModuleEnabled();
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:180'],
            'type' => ['sometimes', 'string', 'max:64'],
            'is_required' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'field_order' => ['sometimes', 'integer'],
            'form_group_id' => ['nullable', 'uuid'],
            'options_json' => ['nullable', 'array'],
        ]);

        $model = DynField::query()->findOrFail($field);

        return $this->ok($schema->presentField($schema->updateField($model, $data, $actor)));
    }
}
