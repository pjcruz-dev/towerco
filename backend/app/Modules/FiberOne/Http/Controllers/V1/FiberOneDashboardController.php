<?php

declare(strict_types=1);

namespace App\Modules\FiberOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\FiberOne\Services\FiberOneDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FiberOneDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, FiberOneDashboardService $service): JsonResponse
    {
        abort_unless($request->user()?->can('fiber_one:view'), 403);

        return $this->ok($service->build());
    }
}
