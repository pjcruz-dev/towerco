<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynEntityShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        return $this->ok($service->presentEntity($model, true));
    }
}
