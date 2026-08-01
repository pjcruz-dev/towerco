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

class RolloutSiteLicenseRecordedController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutProgramService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'site_license_executed_date' => ['required', 'date'],
            'site_license_remarks' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $updated = $service->recordSiteLicense(
            $rollout,
            Carbon::parse($data['site_license_executed_date']),
            $data['site_license_remarks'] ?? null,
        );

        return $this->ok($presenter->detail($updated));
    }
}
