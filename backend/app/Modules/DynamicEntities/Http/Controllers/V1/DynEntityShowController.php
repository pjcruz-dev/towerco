<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynSchemaAccess;
use App\Modules\Ticketing\Services\TicketingSchemaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynEntityShowController extends AbstractApiController
{
    public function __invoke(Request $request, string $entity, DynEntityAdminService $service): JsonResponse
    {
        $model = DynEntity::query()
            ->where(function ($q) use ($entity): void {
                $q->where('id', $entity)->orWhere('slug', $entity);
            })
            ->firstOrFail();

        abort_unless(DynSchemaAccess::canView($request->user(), $model), 403);

        if ($model->slug === TicketingSchemaService::SLUG) {
            app(TicketingSchemaService::class)->ensureListColumns();
            $model->refresh();
        }

        return $this->ok($service->presentEntity($model, true));
    }
}
