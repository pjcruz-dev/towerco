<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynEntityHookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEntityHookIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DynEntityHookService $service): JsonResponse
    {
        abort_unless($request->user()?->can('entity_hooks:manage'), 403);

        $rows = $service->list($request->query('entity_slug') ? (string) $request->query('entity_slug') : null);
        $stats = $service->stats();

        return $this->okWithMeta($rows, [
            'total' => count($rows),
            'stats' => $stats,
        ]);
    }
}
