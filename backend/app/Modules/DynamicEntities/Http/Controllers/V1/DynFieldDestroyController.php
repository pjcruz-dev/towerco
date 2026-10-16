<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynFieldDestroyController extends AbstractApiController
{
    public function __invoke(Request $request, string $field, DynEntityAdminService $service): JsonResponse
    {
        $model = DynField::query()->whereKey($field)->firstOrFail();
        DynSchemaAccess::assertCanManageField($request->user(), $model);
        $actor = $request->user();
        $service->deleteField($model, $actor instanceof TenantUser ? $actor : null);

        return $this->ok(['deleted' => true, 'id' => $field]);
    }
}
