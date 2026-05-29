<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\SiteCandidate;
use App\Modules\Rollout\Services\RolloutProgramPresenter;
use App\Modules\Rollout\Services\SiteCandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteCandidateSelectController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        SiteCandidate $candidate,
        SiteCandidateService $service,
        RolloutProgramPresenter $presenter,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:saq:manage'), 403);

        $program = $service->select($candidate);

        return $this->ok($presenter->detail($program));
    }
}
