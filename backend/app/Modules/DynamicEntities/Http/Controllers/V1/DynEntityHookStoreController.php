<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynEntityHookService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEntityHookStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynEntityHookService $service): JsonResponse
    {
        abort_unless($request->user()?->can('entity_hooks:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:5000'],
            'entity_slug' => ['required', 'string', 'max:160'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'max:40'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'definition_json' => ['required', 'array'],
        ]);

        return $this->ok($service->create($data, $actor), 201);
    }
}
