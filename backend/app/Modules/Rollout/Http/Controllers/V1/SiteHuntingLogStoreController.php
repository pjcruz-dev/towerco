<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutMediaAttachmentService;
use App\Modules\Rollout\Services\SiteHuntingLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiteHuntingLogStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        SiteHuntingLogService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:saq:manage'), 403);

        $data = $request->validate(array_merge([
            'client_draft_id' => ['sometimes', 'nullable', 'uuid'],
            'log_date' => ['sometimes', 'date'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'candidate_ids' => ['sometimes', 'array'],
            'candidate_ids.*' => ['uuid'],
            'candidates_identified_count' => ['sometimes', 'integer', 'min:0'],
        ], RolloutMediaAttachmentService::photoLinksRules()));

        $result = $service->upsert($rollout, $data);
        $log = $result['record'];

        $payload = [
            'id' => $log->id,
            'log_date' => $log->log_date?->toDateString(),
        ];

        return $result['created']
            ? $this->created($payload)
            : $this->ok($payload);
    }
}
