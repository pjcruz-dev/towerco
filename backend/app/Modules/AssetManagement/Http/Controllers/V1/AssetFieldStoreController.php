<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetSchemaService;
use App\Modules\AssetManagement\Support\AssetActor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetFieldStoreController extends AbstractApiController
{
    public function __invoke(Request $request, AssetSchemaService $schema): JsonResponse
    {
        abort_unless($request->user()?->can('assets:fields:manage'), 403);
        $data = $request->validate([
            'label' => ['required', 'string', 'max:180'],
            'name' => ['nullable', 'string', 'max:128'],
            'type' => ['nullable', 'string', 'max:64'],
            'is_required' => ['sometimes', 'boolean'],
            'show_in_table' => ['sometimes', 'boolean'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'field_order' => ['sometimes', 'integer'],
            'form_group_id' => ['nullable', 'uuid'],
        ]);

        return $this->created($schema->createField($data, AssetActor::from($request))->toArray());
    }
}
