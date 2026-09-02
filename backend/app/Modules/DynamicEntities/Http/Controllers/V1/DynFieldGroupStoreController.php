<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldGroupStoreController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'applies_to_form' => ['sometimes', 'boolean'],
            'applies_to_view' => ['sometimes', 'boolean'],
            'start_collapsed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $actor = $request->user();
        $group = $service->createFieldGroup($model, $data, $actor instanceof \App\Modules\Identity\Models\TenantUser ? $actor : null);

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
        ]);
    }
}
