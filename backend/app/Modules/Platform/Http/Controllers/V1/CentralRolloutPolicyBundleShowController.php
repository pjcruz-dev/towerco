<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use Illuminate\Http\JsonResponse;

class CentralRolloutPolicyBundleShowController extends AbstractApiController
{
    public function __invoke(RolloutPolicyBundle $rolloutPolicyBundle, RolloutPolicyBundleService $service): JsonResponse
    {
        return $this->ok($service->present($service->find($rolloutPolicyBundle->id)));
    }
}
