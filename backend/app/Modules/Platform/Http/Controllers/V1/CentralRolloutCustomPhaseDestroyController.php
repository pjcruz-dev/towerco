<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\RolloutCustomPhase;
use App\Modules\Platform\Services\RolloutCustomPhaseService;
use Illuminate\Http\JsonResponse;

class CentralRolloutCustomPhaseDestroyController extends AbstractApiController
{
    public function __invoke(RolloutCustomPhase $rolloutCustomPhase, RolloutCustomPhaseService $service): JsonResponse
    {
        $phase = $service->deactivate($service->find($rolloutCustomPhase->id));

        return $this->ok($service->present($phase));
    }
}
