<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutPermitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RolloutPermitSyncController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        RolloutPermitService $permits,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:rollout:manage'), 403);

        $data = $request->validate([
            'permits' => ['required', 'array'],
            'permits.*.permit_type' => ['required', 'string', 'max:64'],
            'permits.*.applied_date' => ['sometimes', 'nullable', 'date'],
            'permits.*.secured_date' => ['sometimes', 'nullable', 'date'],
            'permits.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        return $this->ok($permits->syncForProgram($rollout, $data['permits']));
    }
}
