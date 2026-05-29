<?php

declare(strict_types=1);

namespace App\Modules\TowerOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\TowerOne\Services\TowerOneDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TowerOneDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, TowerOneDashboardService $service): JsonResponse
    {
        abort_unless($request->user()?->can('tower_one:view'), 403);

        return $this->ok($service->build());
    }
}
