<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\RolloutPolicyBundle;
use App\Modules\Platform\Services\RolloutPolicyBundleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutPolicyBundleUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutPolicyBundle $rolloutPolicyBundle,
        RolloutPolicyBundleService $service,
    ): JsonResponse {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'timeline_templates' => ['sometimes', 'array'],
            'hidden_phases' => ['sometimes', 'array'],
            'gate_approval_policies' => ['sometimes', 'array'],
            'email_notification_policies' => ['sometimes', 'array'],
            'delivery_periods' => ['sometimes', 'array'],
            'changelog' => ['sometimes', 'nullable', 'string'],
        ]);

        $bundle = $service->updateDraft($service->find($rolloutPolicyBundle->id), $data);

        return $this->ok($service->present($bundle));
    }
}
