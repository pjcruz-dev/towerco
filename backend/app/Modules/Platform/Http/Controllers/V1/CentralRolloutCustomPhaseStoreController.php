<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\RolloutCustomPhaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutCustomPhaseStoreController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutCustomPhaseService $service): JsonResponse
    {
        $data = $request->validate([
            'phase_key' => ['required', 'string', 'max:64'],
            'label' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'owner_role' => ['nullable', 'string', 'max:64'],
            'default_anchor' => ['sometimes', 'string', 'in:endorsement,tssr_approved'],
            'default_working_day_start' => ['sometimes', 'integer', 'min:0'],
            'default_working_day_end' => ['sometimes', 'integer', 'min:0'],
            'default_gate' => ['nullable', 'string', 'max:255'],
            'counts_toward_sla' => ['sometimes', 'boolean'],
            'applicable_templates' => ['required', 'array', 'min:1'],
            'applicable_templates.*' => ['string', 'in:bts,rtb,colocation'],
        ]);

        $phase = $service->create($data);

        return $this->ok($service->present($phase), 201);
    }
}
