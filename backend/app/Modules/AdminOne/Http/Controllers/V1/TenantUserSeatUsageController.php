<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSeatLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantUserSeatUsageController extends AbstractApiController
{
    public function __invoke(Request $request, TenantSeatLimitService $seats): JsonResponse
    {
        abort_unless($request->user()?->can('user:manage'), 403);

        return $this->ok($seats->usageSnapshot());
    }
}
