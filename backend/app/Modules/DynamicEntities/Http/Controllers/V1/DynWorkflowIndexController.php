<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynWorkflowIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DynWorkflowService $service): JsonResponse
    {
        abort_unless($request->user()?->can('workflows:manage'), 403);

        $rows = $service->list($request->query('entity_slug') ? (string) $request->query('entity_slug') : null);

        return $this->okWithMeta($rows, ['total' => count($rows)]);
    }
}
