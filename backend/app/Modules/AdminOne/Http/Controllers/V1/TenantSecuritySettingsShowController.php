<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantSecuritySettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantSecuritySettingsShowController extends AbstractApiController
{
    public function __invoke(Request $request, TenantSecuritySettingsService $service): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        return $this->ok($service->show());
    }
}
