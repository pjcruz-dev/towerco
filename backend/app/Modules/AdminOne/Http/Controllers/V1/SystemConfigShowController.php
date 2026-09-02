<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSystemConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemConfigShowController extends AbstractApiController
{
    public function __invoke(Request $request, TenantSystemConfigService $service): JsonResponse
    {
        abort_unless($request->user()?->can('system:manage'), 403);

        return $this->ok($service->get());
    }
}
