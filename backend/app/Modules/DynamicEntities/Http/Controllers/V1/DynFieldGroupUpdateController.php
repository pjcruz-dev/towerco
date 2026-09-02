<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldGroupUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $group, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $model = DynFieldGroup::query()->whereKey($group)->firstOrFail();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:64'],
            'applies_to_form' => ['sometimes', 'boolean'],
            'applies_to_view' => ['sometimes', 'boolean'],
            'start_collapsed' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $actor = $request->user();
        $updated = $service->updateFieldGroup($model, $data, $actor instanceof \App\Modules\Identity\Models\TenantUser ? $actor : null);

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
        ]);
    }
}
