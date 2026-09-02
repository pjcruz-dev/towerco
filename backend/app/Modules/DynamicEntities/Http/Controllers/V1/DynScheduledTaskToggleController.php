<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynScheduledTask;
use App\Modules\DynamicEntities\Services\DynScheduledTaskService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynScheduledTaskToggleController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynScheduledTask $task,
        DynScheduledTaskService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('automation:manage'), 403);
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        return $this->ok($service->toggle($task, $actor));
    }
}
