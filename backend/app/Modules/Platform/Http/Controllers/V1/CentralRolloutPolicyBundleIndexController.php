<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutPolicyBundleIndexController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutPolicyBundleService $service): JsonResponse
    {
        $status = (string) $request->query('status', 'all');
        $bundles = $service->list($status === 'all' ? null : $status);

        return $this->ok([
            'policies' => collect($bundles)->map(fn ($bundle) => $service->present($bundle))->values()->all(),
        ]);
    }
}
