<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Billing\Services\PlatformBillingInsightsService;
use Illuminate\Http\JsonResponse;

final class CentralPlatformBillingInsightsController extends AbstractApiController
{
    public function __invoke(PlatformBillingInsightsService $insights): JsonResponse
    {
        return $this->ok($insights->build());
    }
}
