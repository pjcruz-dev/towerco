<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutGeographyLookup;
use App\Modules\Rollout\Services\RolloutGeographyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGeographyLookupDestroyController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutGeographyLookup $geography,
        RolloutGeographyLookupService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $service->delete($geography);

        return $this->noContent();
    }
}
