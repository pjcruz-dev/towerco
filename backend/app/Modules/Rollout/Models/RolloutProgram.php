<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RolloutProgram extends Model
{
    use HasUuids;

    protected $connection = 'tenant';

    protected $fillable = [
        'parent_rollout_id',
        'playbook_version',
        'rollout_ref',
        'tco_site_id',
        'site_id',
        'project_id',
        'mno',
        'project_type',
        'endorsement_ref',
        'endorsement_date',
        'search_ring_name',
        'region',
        'territory',
        'status',
        'mno_anchor_site_id',
        'alliance_tag',
        'area',
        'site_license_remarks',
        'energization_tempo_date',
        'rfti_signed_tempo_date',
        'cancellation_reason',
        'cancelled_at',
        'tssr_approved_date',
        'doa_execution_date',
        'site_license_executed_date',
        'sla_working_days',
        'target_rfi_working_date',
        'actual_rfi_date',
        'sla_variance_working_days',
        'saq_owner_id',
        'cme_pm_id',
        'pmo_owner_id',
    ];

    protected function casts(): array
    {
        return [
            'endorsement_date' => 'date',
            'tssr_approved_date' => 'date',
            'doa_execution_date' => 'date',
            'site_license_executed_date' => 'date',
            'energization_tempo_date' => 'date',
            'rfti_signed_tempo_date' => 'date',
            'target_rfi_working_date' => 'date',
            'actual_rfi_date' => 'date',
            'cancelled_at' => 'datetime',
            'sla_working_days' => 'integer',
            'sla_variance_working_days' => 'integer',
        ];
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_rollout_id');
    }

    /** @return HasMany<RolloutProgram, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_rollout_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\ProjectOne\Models\Project::class, 'project_id');
    }

    /** @return HasMany<RolloutTimelinePhase, $this> */
    public function timelinePhases(): HasMany
    {
        return $this->hasMany(RolloutTimelinePhase::class)->orderBy('sort_order');
    }

    /** @return HasMany<SiteCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(SiteCandidate::class)->orderBy('candidate_number');
    }

    /** @return HasMany<SiteHuntingDailyLog, $this> */
    public function huntingLogs(): HasMany
    {
        return $this->hasMany(SiteHuntingDailyLog::class)->orderByDesc('log_date');
    }

    /** @return HasMany<CmeDailyReport, $this> */
    public function cmeReports(): HasMany
    {
        return $this->hasMany(CmeDailyReport::class)->orderByDesc('report_date');
    }

    /** @return HasOne<SiteProfitabilityRecord, $this> */
    public function profitability(): HasOne
    {
        return $this->hasOne(SiteProfitabilityRecord::class);
    }

    /** @return HasMany<RolloutPermit, $this> */
    public function permits(): HasMany
    {
        return $this->hasMany(RolloutPermit::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function saqOwner(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'saq_owner_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function pmoOwner(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'pmo_owner_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function cmePm(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'cme_pm_id');
    }
}
