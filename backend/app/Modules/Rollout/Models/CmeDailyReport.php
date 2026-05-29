<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmeDailyReport extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'timeline_phase_id',
        'client_draft_id',
        'report_date',
        'day_number',
        'construction_working_days_total',
        'submitted_by_id',
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
        'photo_links',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'day_number' => 'integer',
            'construction_working_days_total' => 'integer',
            'workforce_count' => 'integer',
            'manhours_today' => 'integer',
            'manhours_cumulative' => 'integer',
            'physical_progress_pct' => 'decimal:2',
            'physical_progress_plan_pct' => 'decimal:2',
            'toolbox_meeting_held' => 'boolean',
            'photo_links' => 'array',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class);
    }

    /** @return BelongsTo<RolloutTimelinePhase, $this> */
    public function timelinePhase(): BelongsTo
    {
        return $this->belongsTo(RolloutTimelinePhase::class, 'timeline_phase_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'submitted_by_id');
    }
}
