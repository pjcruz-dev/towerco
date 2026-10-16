<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynFieldGroup;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldGroupDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, string $group, DynEntityAdminService $service): JsonResponse
    {
        $model = DynFieldGroup::query()->whereKey($group)->firstOrFail();
        DynSchemaAccess::assertCanManageGroup($request->user(), $model);
        $actor = $request->user();
        $service->deleteFieldGroup($model, $actor instanceof TenantUser ? $actor : null);

        return $this->ok(['deleted' => true, 'id' => $group]);
    }
}
