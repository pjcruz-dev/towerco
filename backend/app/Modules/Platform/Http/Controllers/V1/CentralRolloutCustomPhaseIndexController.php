<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Platform\Services\RolloutCustomPhaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CentralRolloutCustomPhaseIndexController extends AbstractApiController
{
    public function __invoke(Request $request, RolloutCustomPhaseService $service): JsonResponse
    {
        $template = (string) $request->query('template', 'all');
        $includeInactive = filter_var($request->query('include_inactive', false), FILTER_VALIDATE_BOOL);

        $phases = $service->list($template === 'all' ? null : $template, ! $includeInactive);

        return $this->ok([
            'phases' => collect($phases)->map(fn ($phase) => $service->present($phase))->values()->all(),
        ]);
    }
}
