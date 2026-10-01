<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRecordShowController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $record,
        DynRecordService $service,
        DynRoleAccessService $access,
    ): JsonResponse {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        $model = DynRecord::query()
            ->with('entity.fields', 'entity.fieldGroups')
            ->whereKey($record)
            ->where('is_deleted', false)
            ->firstOrFail();

        $entity = $model->entity;
        abort_unless($entity !== null, 404);

        $user = $request->user();
        if ($user instanceof TenantUser) {
            abort_unless($access->canEntity($user, (string) $entity->slug, 'view'), 403);
        }

        return $this->ok($service->presentDetail($entity, $model));
    }
}
