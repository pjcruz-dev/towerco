<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynRelationshipService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRelationshipEdgeDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, string $field, DynRelationshipService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $force = $request->boolean('force');
        $softDisable = $request->boolean('soft_disable');

        return $this->ok($service->deleteEdge($field, $actor, $force, $softDisable));
    }
}
