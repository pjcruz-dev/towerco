<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use Illuminate\Http\JsonResponse;

class CentralRolloutPolicyBundlePublishController extends AbstractApiController
{
    public function __invoke(RolloutPolicyBundle $rolloutPolicyBundle, RolloutPolicyBundleService $service): JsonResponse
    {
        $bundle = $service->publish($service->find($rolloutPolicyBundle->id));

        return $this->ok($service->present($bundle));
    }
}
