<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRecordUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $record,
        DynRecordService $service,
        DynRoleAccessService $access,
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($actor instanceof TenantUser, 403);

        $model = DynRecord::query()
            ->with('entity')
            ->whereKey($record)
            ->where('is_deleted', false)
            ->firstOrFail();

        $entity = $model->entity;
        abort_unless($entity !== null, 404);
        abort_unless(DynSchemaAccess::allowsModule($actor, $entity, 'dynamic_entities:records:manage'), 403);
        abort_unless($access->canEntity($actor, (string) $entity->slug, 'edit'), 403, __('Your role does not allow editing records for this entity.'));

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
            'values' => ['sometimes', 'array'],
            'parent_record_id' => ['nullable', 'uuid'],
            'assigned_user_id' => ['nullable', 'uuid'],
        ]);

        $nextStatus = array_key_exists('status', $data) ? (is_string($data['status']) ? $data['status'] : null) : null;
        if (isset($data['values']['status']) && is_scalar($data['values']['status'])) {
            $nextStatus = (string) $data['values']['status'];
        }
        if ($nextStatus !== null) {
            abort_unless(
                $access->statusAllowed($actor, (string) $entity->slug, $nextStatus),
                403,
                __('Your role cannot set that status.'),
            );
        }

        $updated = $service->update($model, $data, $actor);

        return $this->ok($service->presentDetail($entity, $updated));
    }
}
