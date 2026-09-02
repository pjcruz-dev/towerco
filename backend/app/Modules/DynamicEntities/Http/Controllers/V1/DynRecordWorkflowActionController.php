<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\DynRecordWorkflowActionService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRecordWorkflowActionController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $record,
        string $action,
        DynRecordWorkflowActionService $service,
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
        $slug = (string) $entity->slug;
        abort_unless($access->canEntity($actor, $slug, 'edit'), 403, __('Your role does not allow editing records for this entity.'));
        abort_unless($access->canWorkflow($actor, $slug, $action), 403, __('Your role does not allow this workflow action.'));

        $detail = $service->run($model, $action, $actor);

        return $this->ok($detail);
    }
}
