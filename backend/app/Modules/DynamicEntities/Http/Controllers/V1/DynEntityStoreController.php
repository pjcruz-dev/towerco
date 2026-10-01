<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynModulePack;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynEntityStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:entities:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:128'],
            'description' => ['nullable', 'string'],
            'module_pack' => ['sometimes', 'string', 'in:'.implode(',', DynModulePack::all())],
            'storage_mode' => ['sometimes', 'string', 'max:32'],
            'is_location_based' => ['sometimes', 'boolean'],
            'source_linked_table' => ['nullable', 'string', 'max:128'],
            'related_tabs_json' => ['nullable', 'array'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $entity = $service->createEntity($data, $actor);

        return $this->created($service->presentEntity($entity, true));
    }
}
