<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\AtcExecutiveDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AtcExecutiveDashboardController extends AbstractApiController
{
    public function __invoke(Request $request, AtcExecutiveDashboardService $service): JsonResponse
    {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        return $this->ok($service->build());
    }
}
