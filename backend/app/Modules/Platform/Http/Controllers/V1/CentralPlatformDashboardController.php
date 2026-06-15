<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\PlatformDashboardService;
use Illuminate\Http\JsonResponse;

class CentralPlatformDashboardController extends AbstractApiController
{
    public function __invoke(PlatformDashboardService $service): JsonResponse
    {
        return $this->ok($service->build());
    }
}
