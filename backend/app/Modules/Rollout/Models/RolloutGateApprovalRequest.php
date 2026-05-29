<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolloutGateApprovalRequest extends Model
{
    use HasUuids;

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $connection = 'tenant';

    protected $fillable = [
        'rollout_program_id',
        'rollout_timeline_phase_id',
        'phase_key',
        'gate_label',
        'status',
        'current_step',
        'approval_chain',
        'step_log',
        'request_notes',
        'rejection_notes',
        'requested_by_id',
        'submitted_at',
        'current_step_started_at',
        'last_escalated_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_chain' => 'array',
            'step_log' => 'array',
            'current_step' => 'integer',
            'submitted_at' => 'datetime',
            'current_step_started_at' => 'datetime',
            'last_escalated_at' => 'datetime',
            'completed_at' => 'datetime',
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
        return $this->belongsTo(RolloutTimelinePhase::class, 'rollout_timeline_phase_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'requested_by_id');
    }

    public function currentApproverRole(): ?string
    {
        $chain = $this->approval_chain ?? [];

        return isset($chain[$this->current_step]) ? (string) $chain[$this->current_step] : null;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_IN_REVIEW;
    }
}
