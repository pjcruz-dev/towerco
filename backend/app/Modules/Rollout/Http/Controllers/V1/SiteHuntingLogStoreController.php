<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutMediaAttachmentService;
use App\Modules\Rollout\Services\SiteHuntingLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SiteHuntingLogStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        SiteHuntingLogService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:saq:manage'), 403);

        $request->merge([
            'candidates_identified_count' => $this->normalizeCandidatesIdentifiedCountInput(
                $request->input('candidates_identified_count'),
            ),
        ]);

        $data = $request->validate(array_merge([
            'client_draft_id' => ['sometimes', 'nullable', 'uuid'],
            'log_date' => ['sometimes', 'date'],
            'summary' => ['sometimes', 'nullable', 'string'],
            'candidate_ids' => ['sometimes', 'array'],
            'candidate_ids.*' => ['uuid'],
            'candidates_identified_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
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

    private function normalizeCandidatesIdentifiedCountInput(mixed $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_int($raw)) {
            return max(0, $raw);
        }

        if (is_float($raw) || is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        if (is_string($raw)) {
            $trimmed = trim($raw);
            if ($trimmed === '') {
                return null;
            }

            if (ctype_digit($trimmed)) {
                return (int) $trimmed;
            }

            if (preg_match('/\d+/', $trimmed, $matches) === 1) {
                return (int) $matches[0];
            }
        }

        throw ValidationException::withMessages([
            'candidates_identified_count' => [__('Enter how many candidates were identified today (numbers only, e.g. 3).')],
        ]);
    }
}
