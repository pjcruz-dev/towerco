<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RolloutSiteProfileUpdateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutProgramService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'full_address' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ]);

        $updated = $service->updateSiteProfile($rollout, $data);

        return $this->ok($presenter->detail($updated));
    }
}
