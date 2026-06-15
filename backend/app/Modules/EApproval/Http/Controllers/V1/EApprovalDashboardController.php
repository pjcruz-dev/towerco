<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\EApproval\Services\EApprovalDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EApprovalDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, EApprovalDashboardService $service): JsonResponse
    {
        abort_unless($request->user()?->can('e_approval:view'), 403);

        return $this->ok($service->build($request->user()));
    }
}
