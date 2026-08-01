<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Services\RolloutGeographyLookupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutGeographyLookupSeedDefaultsController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutGeographyLookupService $service): JsonResponse
    {
        abort_unless($request->user()?->can('project_one:playbook:configure'), 403);

        $result = $service->seedDefaults();

        return $this->ok([
            'created' => $result['created'],
            'total' => $result['total'],
            'items' => $service->list(),
        ]);
    }
}
