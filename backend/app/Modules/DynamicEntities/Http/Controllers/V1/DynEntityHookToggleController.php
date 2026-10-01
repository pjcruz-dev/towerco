<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntityHook;
use App\Modules\DynamicEntities\Services\DynEntityHookService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DynEntityHookToggleController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        DynEntityHook $hook,
        DynEntityHookService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('entity_hooks:manage'), 403);
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        return $this->ok($service->toggle($hook, $actor));
    }
}
