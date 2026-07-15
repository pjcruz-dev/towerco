<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantBillingReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantBillingShowController extends AbstractApiController
{
    public function __invoke(Request $request, TenantBillingReadService $service): JsonResponse
    {
        abort_unless($request->user()?->can('billing:view'), 403);

        return $this->ok($service->snapshot());
    }
}
