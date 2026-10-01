<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $field, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $model = DynField::query()->whereKey($field)->firstOrFail();

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:'.implode(',', DynFieldType::all())],
            'is_required' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'is_filterable' => ['sometimes', 'boolean'],
            'is_key' => ['sometimes', 'boolean'],
            'calculate_totals' => ['sometimes', 'boolean'],
            'options_json' => ['nullable', 'array'],
            'target_entity_id' => ['nullable', 'uuid'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'formula_definition' => ['nullable', 'string'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'field_order' => ['sometimes', 'integer', 'min:0'],
            'form_group_id' => ['nullable', 'uuid'],
            'view_group_id' => ['nullable', 'uuid'],
            'conditional_rules_json' => ['nullable', 'array'],
            'is_virtual' => ['sometimes', 'boolean'],
            'system_column' => ['nullable', 'string', 'max:128'],
        ]);

        $actor = $request->user();
        $updated = $service->updateField($model, $data, $actor instanceof TenantUser ? $actor : null);

        return $this->ok($service->presentField($updated));
    }
}
