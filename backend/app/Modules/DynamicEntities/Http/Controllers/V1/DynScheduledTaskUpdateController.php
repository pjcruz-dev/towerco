<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynScheduledTask;
use App\Modules\DynamicEntities\Services\DynScheduledTaskService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynScheduledTaskUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynScheduledTask $task,
        DynScheduledTaskService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('automation:manage'), 403);
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'command_key' => ['sometimes', 'string', 'max:120'],
            'schedule' => ['sometimes', 'string', 'max:64'],
            'cron_expression' => ['sometimes', 'nullable', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->ok($service->update($task, $data, $actor));
    }
}
