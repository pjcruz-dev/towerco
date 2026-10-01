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

final class DynRecordDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $record,
        DynRecordService $service,
        DynRoleAccessService $access,
    ): JsonResponse {
        abort_unless($request->user()?->can('dynamic_entities:records:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = DynRecord::query()
            ->with('entity')
            ->whereKey($record)
            ->where('is_deleted', false)
            ->firstOrFail();

        $entity = $model->entity;
        abort_unless($entity !== null, 404);
        abort_unless($access->canEntity($actor, (string) $entity->slug, 'delete'), 403, __('Your role does not allow deleting records for this entity.'));

        $service->softDelete($model, $actor);

        return $this->ok(['id' => $model->id, 'deleted' => true]);
    }
}
