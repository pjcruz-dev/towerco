<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRecordBulkController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        DynRecordService $service,
        DynRoleAccessService $access,
    ): JsonResponse {
        abort_unless($request->user()?->can('dynamic_entities:records:manage'), 403);

        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        $validated = $request->validate([
            'action' => ['required', 'string', 'in:delete,duplicate,update'],
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['required', 'uuid'],
            'status' => ['sometimes', 'nullable', 'string', 'max:64'],
            'values' => ['sometimes', 'array'],
        ]);

        $action = (string) $validated['action'];
        $ids = array_map('strval', $validated['ids']);

        if ($action === 'delete') {
            abort_unless($access->canEntity($actor, (string) $model->slug, 'delete'), 403, __('Your role does not allow deleting records for this entity.'));
            $result = $service->bulkSoftDelete($model, $ids, $actor);

            return $this->ok($result);
        }

        if ($action === 'duplicate') {
            abort_unless($access->canEntity($actor, (string) $model->slug, 'create'), 403, __('Your role does not allow duplicating records for this entity.'));
            $result = $service->bulkDuplicate($model, $ids, $actor);

            return $this->ok($result);
        }

        abort_unless($access->canEntity($actor, (string) $model->slug, 'edit'), 403, __('Your role does not allow editing records for this entity.'));
        $payload = [];
        if (array_key_exists('status', $validated)) {
            $payload['status'] = $validated['status'];
        }
        if (isset($validated['values']) && is_array($validated['values'])) {
            $payload['values'] = $validated['values'];
        }
        abort_unless($payload !== [], 422, 'Provide status and/or values to update.');

        $result = $service->bulkUpdate($model, $ids, $payload, $actor);

        return $this->ok($result);
    }
}
