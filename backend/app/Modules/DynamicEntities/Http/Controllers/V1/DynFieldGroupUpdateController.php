<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldGroupUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $group, DynEntityAdminService $service): JsonResponse
    {
        $model = DynFieldGroup::query()->whereKey($group)->firstOrFail();
        DynSchemaAccess::assertCanManageGroup($request->user(), $model);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'applies_to_form' => ['sometimes', 'boolean'],
            'applies_to_view' => ['sometimes', 'boolean'],
            'start_collapsed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
        ]);

        $actor = $request->user();
        $updated = $service->updateFieldGroup($model, $data, $actor instanceof TenantUser ? $actor : null);

        return $this->ok([
            'id' => $updated->id,
            'entity_id' => $updated->entity_id,
            'name' => $updated->name,
            'description' => $updated->description,
            'icon' => $updated->icon,
            'applies_to_form' => $updated->applies_to_form,
            'applies_to_view' => $updated->applies_to_view,
            'start_collapsed' => $updated->start_collapsed,
            'sort_order' => $updated->sort_order,
            'column_span' => (int) ($updated->column_span ?: 12),
        ]);
    }
}
