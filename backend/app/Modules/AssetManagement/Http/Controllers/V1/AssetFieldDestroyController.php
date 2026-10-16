<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetSchemaService;
use App\Modules\DynamicEntities\Models\DynField;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetFieldDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, string $field, AssetSchemaService $schema): JsonResponse
    {
        abort_unless($request->user()?->can('assets:fields:manage'), 403);
        $schema->deleteField(DynField::query()->findOrFail($field));

        return $this->ok(['deleted' => true]);
    }
}
