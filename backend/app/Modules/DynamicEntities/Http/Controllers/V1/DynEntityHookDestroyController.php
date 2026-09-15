<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntityHook;
use App\Modules\DynamicEntities\Services\DynEntityHookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEntityHookDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, DynEntityHook $hook, DynEntityHookService $service): JsonResponse
    {
        abort_unless($request->user()?->can('entity_hooks:manage'), 403);

        $service->destroy($hook);

        return $this->ok(['deleted' => true]);
    }
}
