<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldGroupDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, string $group, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:fields:manage'), 403);

        $model = DynFieldGroup::query()->whereKey($group)->firstOrFail();
        $actor = $request->user();
        $service->deleteFieldGroup($model, $actor instanceof TenantUser ? $actor : null);

        return $this->ok(['deleted' => true, 'id' => $group]);
    }
}
