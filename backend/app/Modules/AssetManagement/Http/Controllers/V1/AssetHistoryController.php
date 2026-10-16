<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use App\Modules\AssetManagement\Services\AssetRegistryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetHistoryController extends AbstractApiController
{
    public function __invoke(Request $request, string $asset, AssetRegistryService $registry, AssetQueryService $query): JsonResponse
    {
        abort_unless($request->user()?->can('assets:view') || $request->user()?->can('assets:self:view'), 403);
        $record = $registry->findByTagOrId($asset);

        return $this->ok($query->present($record)['events']);
    }
}
