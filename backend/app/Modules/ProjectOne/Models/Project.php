<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasUuids;

    protected $table = 'projects';

    protected $fillable = [
        'site_id',
        'name',
        'project_manager_id',
        'status',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'project_manager_id');
    }

    /** @return HasMany<ProjectApproval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(ProjectApproval::class, 'project_id');
    }

    /** @return HasMany<Milestone, $this> */
    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'project_id');
    }

    /** @return HasMany<RolloutProgram, $this> */
    public function rollouts(): HasMany
    {
        return $this->hasMany(RolloutProgram::class, 'project_id');
    }
}
