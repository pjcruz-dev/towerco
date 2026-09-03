<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynRelationshipService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRelationshipEdgeUpdateController extends AbstractApiController
{
    public function __invoke(Request $request, string $field, DynRelationshipService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $data = $request->validate([
            'label' => ['sometimes', 'string', 'max:255'],
            'target_entity_id' => ['nullable', 'uuid'],
            'related_tab_label' => ['nullable', 'string', 'max:255'],
            'sync_related_tab' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        return $this->ok($service->updateEdge($field, $data, $actor));
    }
}
