<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\DynamicEntities\Services\DynRoleAccessService;
use App\Modules\DynamicEntities\Support\DynRecordQueryFilterParser;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynRecordIndexController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        string $entity,
        DynRecordService $service,
        DynRoleAccessService $access,
    ): JsonResponse {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        $user = $request->user();
        $roleFilters = [];
        $viewOwnUserId = null;
        if ($user instanceof TenantUser) {
            abort_unless($access->canEntity($user, (string) $model->slug, 'view'), 403, __('Your role does not allow viewing records for this entity.'));
            $roleFilters = $access->dataFilterGroups($user, (string) $model->slug);
            if ($access->viewOwnOnly($user, (string) $model->slug)) {
                $viewOwnUserId = (string) $user->id;
            }
        }

        $perPage = (int) $request->integer('per_page', 25);
        $paginator = $service->paginate($model, [
            'search' => $request->query('search'),
            'status' => $request->query('status'),
            'parent_record_id' => $request->query('parent_record_id'),
            'foreign_field' => $request->query('foreign_field'),
            'sort' => $request->query('sort'),
            'filter' => $request->query('filter'),
            'advanced_filter_rules' => DynRecordQueryFilterParser::fromRequest($request, $model),
            'role_data_filter_groups' => $roleFilters,
            'view_own_user_id' => $viewOwnUserId,
        ], $perPage);

        $rows = collect($paginator->items())->map(
            fn ($record) => $service->presentListRow($model, $record)
        )->values()->all();

        return $this->okWithMeta($rows, [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ]);
    }
}
