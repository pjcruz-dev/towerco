<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntityHook;
use App\Modules\DynamicEntities\Services\DynEntityHookService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEntityHookUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynEntityHook $hook,
        DynEntityHookService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('entity_hooks:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'entity_slug' => ['sometimes', 'string', 'max:160'],
            'events' => ['sometimes', 'array', 'min:1'],
            'events.*' => ['string', 'max:40'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:9999'],
            'definition_json' => ['sometimes', 'array'],
        ]);

        return $this->ok($service->update($hook, $data, $actor));
    }
}
