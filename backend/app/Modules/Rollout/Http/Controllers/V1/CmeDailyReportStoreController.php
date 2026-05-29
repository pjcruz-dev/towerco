<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\CmeDailyReportService;
use App\Modules\Rollout\Services\RolloutMediaAttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmeDailyReportStoreController extends AbstractApiController
{
    public function __invoke(
        Request $request,
        RolloutProgram $rollout,
        CmeDailyReportService $service,
    ): JsonResponse {
        abort_unless($request->user()?->can('project_one:cme:manage'), 403);

        $data = $request->validate(array_merge([
            'timeline_phase_id' => ['sometimes', 'nullable', 'uuid', 'exists:rollout_timeline_phases,id'],
            'client_draft_id' => ['sometimes', 'nullable', 'uuid'],
            'report_date' => ['sometimes', 'date'],
            'day_number' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'construction_working_days_total' => ['sometimes', 'integer', 'min:1'],
            'weather_am' => ['sometimes', 'nullable', 'string', 'max:32'],
            'weather_pm' => ['sometimes', 'nullable', 'string', 'max:32'],
            'workforce_count' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'manhours_today' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'manhours_cumulative' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'physical_progress_pct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'physical_progress_plan_pct' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'activities_completed' => ['sometimes', 'nullable', 'string'],
            'activities_planned_tomorrow' => ['sometimes', 'nullable', 'string'],
            'quality_issues' => ['sometimes', 'nullable', 'string'],
            'safety_incidents' => ['sometimes', 'nullable', 'string', 'max:64'],
            'toolbox_meeting_held' => ['sometimes', 'boolean'],
            'lessor_neighbor_issues' => ['sometimes', 'nullable', 'string'],
            'risks_flagged' => ['sometimes', 'nullable', 'string'],
        ], RolloutMediaAttachmentService::photoLinksRules()));

        if (! empty($data['timeline_phase_id'])) {
            $belongs = $rollout->timelinePhases()
                ->whereKey($data['timeline_phase_id'])
                ->exists();
            abort_unless($belongs, 422, 'Timeline phase does not belong to this rollout.');
        }

        $result = $service->upsert($rollout, $data);
        $report = $result['record'];

        $payload = [
            'id' => $report->id,
            'report_date' => $report->report_date?->toDateString(),
            'day_number' => $report->day_number,
        ];

        return $result['created']
            ? $this->created($payload)
            : $this->ok($payload);
    }
}
