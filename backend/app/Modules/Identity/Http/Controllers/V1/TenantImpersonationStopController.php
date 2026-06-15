<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantUserImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantImpersonationStopController extends AbstractApiController
{
    public function __invoke(Request $request, TenantUserImpersonationService $service): JsonResponse
    {
        return $this->ok($service->stop($request));
    }
}
