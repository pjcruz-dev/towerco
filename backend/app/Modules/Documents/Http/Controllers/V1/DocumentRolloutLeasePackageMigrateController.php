<?php

declare(strict_types=1);

namespace App\Modules\Documents\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Documents\Services\DocumentRolloutLeasePackageMigrationService;
use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentRolloutLeasePackageMigrateController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        DocumentRolloutLeasePackageMigrationService $migration,
    ): JsonResponse {
        abort_unless($request->user()?->can('documents:manage'), 403);
        abort_unless($request->user()?->can('project_one:rollout:view'), 403);

        $data = $request->validate([
            'candidate_id' => ['sometimes', 'nullable', 'uuid'],
        ]);

        $result = $migration->migrateRollout(
            $rollout,
            $data['candidate_id'] ?? null,
            $request->user(),
        );

        return $this->ok($result);
    }
}
