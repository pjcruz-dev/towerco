<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\AdminOne\Services\TenantWorkspaceDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantWorkspaceDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, TenantWorkspaceDashboardService $service): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && $user->can('dashboard:view'), 403);

        return $this->ok($service->build($user));
    }
}
