<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldGroupStoreController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, DynEntityAdminService $service): JsonResponse
    {
        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();
        DynSchemaAccess::assertCanManage($request->user(), $model);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'applies_to_form' => ['sometimes', 'boolean'],
            'applies_to_view' => ['sometimes', 'boolean'],
            'start_collapsed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'column_span' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'surface' => ['sometimes', 'string', 'max:32', 'regex:/^[a-z][a-z0-9_]*$/'],
        ]);

        $actor = $request->user();
        $group = $service->createFieldGroup($model, $data, $actor instanceof TenantUser ? $actor : null);

        return $this->created([
            'id' => $group->id,
            'entity_id' => $group->entity_id,
            'name' => $group->name,
            'description' => $group->description,
            'icon' => $group->icon,
            'applies_to_form' => $group->applies_to_form,
            'applies_to_view' => $group->applies_to_view,
            'start_collapsed' => $group->start_collapsed,
            'sort_order' => $group->sort_order,
            'column_span' => (int) ($group->column_span ?: 12),
            'surface' => $group->surface ?: 'overview',
        ]);
    }
}
