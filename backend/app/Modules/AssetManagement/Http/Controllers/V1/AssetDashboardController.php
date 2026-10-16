<?php

declare(strict_types=1);

namespace App\Modules\AssetManagement\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AssetManagement\Services\AssetQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AssetDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, AssetQueryService $query): JsonResponse
    {
        abort_unless($request->user()?->can('assets:view'), 403);

        return $this->ok($query->dashboard());
    }
}
