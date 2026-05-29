<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetOne\Services\AssetOneDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetOneDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, AssetOneDashboardService $service): JsonResponse
    {
        abort_unless($request->user()?->can('asset_one:view'), 403);

        return $this->ok($service->build());
    }
}
