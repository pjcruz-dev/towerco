<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantAppearanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TenantAppearanceShowController extends AbstractApiController
{
    public function __invoke(Request $request, TenantAppearanceService $service): JsonResponse
    {
        abort_unless($request->user()?->can('tenant:manage'), 403);

        return $this->ok($service->show());
    }
}
