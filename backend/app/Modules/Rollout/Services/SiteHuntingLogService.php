<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteHuntingDailyLog;
use App\Modules\Rollout\Support\RolloutFieldCreateResult;

final class SiteHuntingLogService
{
    public function __construct(
        private readonly RolloutMediaAttachmentService $media,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{record: SiteHuntingDailyLog, created: bool}
     */
    public function upsert(RolloutProgram $program, array $input): array
    {
        if (! empty($input['client_draft_id'])) {
            $existing = SiteHuntingDailyLog::query()
                ->where('rollout_program_id', $program->id)
                ->where('client_draft_id', $input['client_draft_id'])
                ->first();

            if ($existing !== null) {
                return RolloutFieldCreateResult::of($existing, false);
            }
        }

        $logDate = $input['log_date'] ?? now()->toDateString();
        $candidateIds = $input['candidate_ids'] ?? [];

        $attributes = [
            'client_draft_id' => $input['client_draft_id'] ?? null,
            'hunter_user_id' => $input['hunter_user_id'] ?? auth()->id(),
            'summary' => $input['summary'] ?? null,
            'candidate_ids' => $candidateIds,
            'candidates_identified_count' => (int) ($input['candidates_identified_count'] ?? count($candidateIds)),
        ];

        if (array_key_exists('photo_links', $input)) {
            $attributes['photo_links'] = $this->media->normalizePhotoLinks(
                is_array($input['photo_links']) ? $input['photo_links'] : null,
                $program->id,
            );
        }

        /** @var SiteHuntingDailyLog|null $existingByDate */
        $existingByDate = SiteHuntingDailyLog::query()
            ->where('rollout_program_id', $program->id)
            ->whereDate('log_date', $logDate)
            ->first();

        /** @var SiteHuntingDailyLog $log */
        $log = SiteHuntingDailyLog::query()->updateOrCreate(
            [
                'rollout_program_id' => $program->id,
                'log_date' => $logDate,
            ],
            $attributes,
        );

        return RolloutFieldCreateResult::of($log->fresh(), $existingByDate === null);
    }
}
