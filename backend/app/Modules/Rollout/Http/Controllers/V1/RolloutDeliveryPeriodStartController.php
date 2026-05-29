<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use App\Modules\Rollout\Services\RolloutProgramService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutDeliveryPeriodStartController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutProgramService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $projectType = match ($rollout->project_type) {
            'rtb' => 'rtb',
            'colocation', 'colo' => 'colocation',
            default => 'bts',
        };

        $updated = match ($projectType) {
            'rtb' => $this->applyRtb($request, $rollout, $service),
            'colocation' => $this->applyColocation($request, $rollout, $service),
            default => $this->applyBts($request, $rollout, $service),
        };

        return $this->ok($presenter->detail($updated));
    }

    private function applyBts(Request $request, RolloutProgram $rollout, RolloutProgramService $service): RolloutProgram
    {
        $data = $request->validate([
            'tssr_approved_date' => ['required', 'date'],
        ]);

        return $service->setTssrApproved($rollout, Carbon::parse($data['tssr_approved_date']));
    }

    private function applyRtb(Request $request, RolloutProgram $rollout, RolloutProgramService $service): RolloutProgram
    {
        $data = $request->validate([
            'doa_execution_date' => ['required', 'date'],
        ]);

        return $service->setDoaExecution($rollout, Carbon::parse($data['doa_execution_date']));
    }

    private function applyColocation(Request $request, RolloutProgram $rollout, RolloutProgramService $service): RolloutProgram
    {
        $data = $request->validate([
            'site_license_executed_date' => ['required', 'date'],
        ]);

        return $service->setSiteLicenseExecuted($rollout, Carbon::parse($data['site_license_executed_date']));
    }
}
