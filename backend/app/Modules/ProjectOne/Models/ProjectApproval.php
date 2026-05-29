<?php

declare(strict_types=1);

namespace App\Modules\ProjectOne\Models;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProjectOne\Models\Project;
use App\Modules\Rollout\Models\RolloutProgram;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectApproval extends Model
{
    use HasUuids;

    protected $table = 'project_approvals';

    protected $fillable = [
        'project_id',
        'rollout_program_id',
        'approval_type',
        'title',
        'requester',
        'submitted_at',
        'sla_risk',
        'attachment_file_ids',
        'status',
        'resolution_notes',
        'resolved_at',
        'resolved_by_id',
    ];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
            'attachment_file_ids' => 'array',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<RolloutProgram, $this> */
    public function rolloutProgram(): BelongsTo
    {
        return $this->belongsTo(RolloutProgram::class, 'rollout_program_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'resolved_by_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDashboardApproval(): array
    {
        $submitted = $this->submitted_at ?? $this->created_at;

        return [
            'id' => (string) $this->id,
            'type' => $this->approval_type,
            'title' => $this->title,
            'requester' => $this->requester,
            'submittedAt' => $submitted?->toIso8601String() ?? '',
            'slaRisk' => $this->sla_risk,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toListRow(): array
    {
        return array_merge($this->toDashboardApproval(), [
            'status' => $this->status,
            'resolvedAt' => $this->resolved_at?->toIso8601String(),
            'resolutionNotes' => $this->resolution_notes,
            'project' => $this->relationLoaded('project') && $this->project
                ? ['id' => $this->project->id, 'name' => $this->project->name]
                : null,
            'rollout' => $this->relationLoaded('rolloutProgram') && $this->rolloutProgram
                ? ['id' => $this->rolloutProgram->id, 'rollout_ref' => $this->rolloutProgram->rollout_ref]
                : null,
            'attachment_file_ids' => $this->attachment_file_ids,
            'resolvedBy' => $this->relationLoaded('resolvedBy') && $this->resolvedBy
                ? ['id' => $this->resolvedBy->id, 'name' => $this->resolvedBy->name]
                : null,
        ]);
    }
}
