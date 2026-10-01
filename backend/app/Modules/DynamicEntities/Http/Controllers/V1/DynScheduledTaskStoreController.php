<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\DynScheduledTaskService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynScheduledTaskStoreController extends AbstractApiController
{
    public function __invoke(Request $request, DynScheduledTaskService $service): JsonResponse
    {
        abort_unless($request->user()?->can('automation:manage'), 403);
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'command_key' => ['required', 'string', 'max:120'],
            'schedule' => ['required', 'string', 'max:64'],
            'cron_expression' => ['nullable', 'string', 'max:64'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return $this->ok($service->create($data, $actor), 201);
    }
}
