<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use App\Modules\Rollout\Services\RolloutProgramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RolloutProgramCancelController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutProgramService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);

        $updated = $service->cancel($rollout, $data['cancellation_reason']);

        return $this->ok($presenter->detail($updated));
    }
}
