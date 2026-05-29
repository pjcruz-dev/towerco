<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Models\RolloutCustomPhase;
use App\Modules\Platform\Services\RolloutCustomPhaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutCustomPhaseUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutCustomPhase $rolloutCustomPhase,
        RolloutCustomPhaseService $service,
    ): JsonResponse {
        $data = $request->validate([
            'phase_key' => ['sometimes', 'string', 'max:64'],
            'label' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_role' => ['nullable', 'string', 'max:64'],
            'default_anchor' => ['sometimes', 'string', 'in:endorsement,tssr_approved'],
            'default_working_day_start' => ['sometimes', 'integer', 'min:0'],
            'default_working_day_end' => ['sometimes', 'integer', 'min:0'],
            'default_gate' => ['nullable', 'string', 'max:255'],
            'counts_toward_sla' => ['sometimes', 'boolean'],
            'applicable_templates' => ['sometimes', 'array', 'min:1'],
            'applicable_templates.*' => ['string', 'in:bts,rtb,colocation'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $phase = $service->update($service->find($rolloutCustomPhase->id), $data);

        return $this->ok($service->present($phase));
    }
}
