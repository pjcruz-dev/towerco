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

final class DynRecordStoreController extends AbstractApiController
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

        abort_unless($access->canEntity($actor, (string) $model->slug, 'create'), 403, __('Your role does not allow creating records for this entity.'));

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:64'],
            'values' => ['required', 'array'],
            'parent_record_id' => ['nullable', 'uuid'],
            'location_id' => ['nullable', 'uuid'],
            'assigned_user_id' => ['nullable', 'uuid'],
            'source_external_id' => ['nullable', 'string', 'max:64'],
        ]);

        $record = $service->create($model, $data, $actor);

        return $this->created($service->presentDetail($model, $record));
    }
}
