<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetSchemaService;
use App\Modules\AssetManagement\Support\AssetActor;
use App\Modules\DynamicEntities\Models\DynField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetFieldUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $field, AssetSchemaService $schema): JsonResponse
    {
        abort_unless($request->user()?->can('assets:fields:manage'), 403);
        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:180'],
            'type' => ['sometimes', 'string', 'max:64'],
            'is_required' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'field_order' => ['sometimes', 'integer'],
            'form_group_id' => ['nullable', 'uuid'],
        ]);
        $model = DynField::query()->findOrFail($field);

        return $this->ok($schema->updateField($model, $data, AssetActor::from($request))->toArray());
    }
}
