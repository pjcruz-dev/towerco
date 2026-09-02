<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynScheduledTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynScheduledTaskIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DynScheduledTaskService $service): JsonResponse
    {
        abort_unless($request->user()?->can('automation:manage'), 403);

        $rows = $service->list();

        return $this->okWithMeta($rows, [
            'total' => count($rows),
            'runner' => $service->meta()['runner'],
            'commands' => $service->meta()['commands'],
            'schedule_presets' => $service->meta()['schedule_presets'],
        ]);
    }
}
