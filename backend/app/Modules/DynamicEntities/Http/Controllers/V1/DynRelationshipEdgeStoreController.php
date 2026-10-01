<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynRelationshipService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRelationshipEdgeStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynRelationshipService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $data = $request->validate([
            'source_entity_id' => ['required', 'uuid'],
            'target_entity_id' => ['required', 'uuid'],
            'label' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:128'],
            'related_tab_label' => ['nullable', 'string', 'max:255'],
            'sync_related_tab' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        return $this->ok($service->upsertEdge($data, $actor), 201);
    }
}
