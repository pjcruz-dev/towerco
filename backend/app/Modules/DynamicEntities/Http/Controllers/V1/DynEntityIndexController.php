<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Support\DynModulePack;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DynEntityIndexController extends AbstractApiController
{
    public function __invoke(Request $request, DynEntityAdminService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        $query = DynEntity::query()->orderBy('module_pack')->orderBy('sort_order')->orderBy('name');

        if ($request->filled('module_pack')) {
            $pack = (string) $request->string('module_pack');
            if (in_array($pack, DynModulePack::all(), true)) {
                $query->where('module_pack', $pack);
            }
        }

        if ($request->boolean('active_only', true)) {
            $query->where('is_active', true);
        }

        $rows = $query->get()->map(fn (DynEntity $e): array => $service->presentEntity($e));

        return $this->ok($rows);
    }
}
