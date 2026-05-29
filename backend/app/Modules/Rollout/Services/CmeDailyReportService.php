<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Services;

use App\Modules\Rollout\Models\CmeDailyReport;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Support\RolloutFieldCreateResult;

final class CmeDailyReportService
{
    public function __construct(
        private readonly RolloutMediaAttachmentService $media,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{record: CmeDailyReport, created: bool}
     */
    public function upsert(RolloutProgram $program, array $input): array
    {
        if (! empty($input['client_draft_id'])) {
            $existing = CmeDailyReport::query()
                ->where('rollout_program_id', $program->id)
                ->where('client_draft_id', $input['client_draft_id'])
                ->first();

            if ($existing !== null) {
                return RolloutFieldCreateResult::of($existing, false);
            }
        }

        $reportDate = $input['report_date'] ?? now()->toDateString();

        /** @var CmeDailyReport|null $existingByDate */
        $existingByDate = CmeDailyReport::query()
            ->where('rollout_program_id', $program->id)
            ->whereDate('report_date', $reportDate)
            ->first();

        /** @var CmeDailyReport $report */
        $report = CmeDailyReport::query()->updateOrCreate(
            [
                'rollout_program_id' => $program->id,
                'report_date' => $reportDate,
            ],
            array_merge($this->fields($input, $program), [
                'timeline_phase_id' => $input['timeline_phase_id'] ?? null,
                'client_draft_id' => $input['client_draft_id'] ?? null,
                'submitted_by_id' => auth()->id(),
            ]),
        );

        return RolloutFieldCreateResult::of($report->fresh(), $existingByDate === null);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function fields(array $input, RolloutProgram $program): array
    {
        $fields = array_intersect_key($input, array_flip([
            'day_number',
            'construction_working_days_total',
            'weather_am',
            'weather_pm',
            'workforce_count',
            'manhours_today',
            'manhours_cumulative',
            'physical_progress_pct',
            'physical_progress_plan_pct',
            'activities_completed',
            'activities_planned_tomorrow',
            'quality_issues',
            'safety_incidents',
            'toolbox_meeting_held',
            'lessor_neighbor_issues',
            'risks_flagged',
        ]));

        if (array_key_exists('photo_links', $input)) {
            $fields['photo_links'] = $this->media->normalizePhotoLinks(
                is_array($input['photo_links']) ? $input['photo_links'] : null,
                $program->id,
            );
        }

        return $fields;
    }
}
