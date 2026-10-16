<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
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
        $model = DynRecord::query()
            ->with('entity.fields', 'entity.fieldGroups')
            ->whereKey($record)
            ->where('is_deleted', false)
            ->first();

        if ($model === null) {
            $directory = DynEntity::query()->where('slug', 'workspace_users')->first();
            abort_unless($directory instanceof DynEntity, 404);
            abort_unless(DynSchemaAccess::allowsModule($request->user(), $directory, 'dynamic_entities:view'), 403);
            $actor = $request->user();
            if ($actor instanceof TenantUser) {
                abort_unless($access->canEntity($actor, 'workspace_users', 'view'), 403);
            }
            $person = $service->presentWorkspaceUser($record);
            abort_unless($person !== null, 404);

            return $this->ok($person);
        }

        $entity = $model->entity;
        abort_unless($entity !== null, 404);
        abort_unless(DynSchemaAccess::allowsModule($request->user(), $entity, 'dynamic_entities:view'), 403);

        $user = $request->user();
        if ($user instanceof TenantUser) {
            abort_unless($access->canEntity($user, (string) $entity->slug, 'view'), 403);
        }

        return $this->ok($service->presentDetail($entity, $model));
    }
}
