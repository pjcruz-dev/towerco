<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynRelationshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRelationshipGraphController extends AbstractApiController
{
    public function __invoke(Request $request, DynRelationshipService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $modulePack = $request->filled('module_pack')
            ? (string) $request->string('module_pack')
            : null;

        return $this->ok($service->graph($modulePack));
    }
}
