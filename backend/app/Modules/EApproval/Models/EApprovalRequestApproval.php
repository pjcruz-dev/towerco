<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EApprovalRequestApproval extends Model
{
    use HasUuids;

    protected $table = 'e_approval_request_approvals';

    protected $fillable = [
        'submission_id',
        'step_id',
        'approver_id',
        'status',
        'approval_cycle',
        'remarks',
        'acted_at',
        'signature',
        'last_reminder_at',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
            'approval_cycle' => 'integer',
            'acted_at' => 'datetime',
            'last_reminder_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** @return BelongsTo<EApprovalSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(EApprovalSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<EApprovalWorkflowStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(EApprovalWorkflowStep::class, 'step_id');
    }

    /** @return BelongsTo<TenantUser, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'approver_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toListRow(): array
    {
        $this->loadMissing(['step', 'approver', 'submission.form']);

        $submissionStatus = $this->submission?->status;
        $approvalStatus = (string) $this->status;
        $displayStatus = $approvalStatus === 'pending'
            ? $approvalStatus
            : ($submissionStatus ?? $approvalStatus);
        if (
            in_array($approvalStatus, ['pending', 'invalidated'], true)
            && in_array((string) $submissionStatus, ['cancelled', 'approved', 'rejected'], true)
        ) {
            $displayStatus = (string) $submissionStatus;
        }

        $cycle = (int) ($this->approval_cycle ?: 1);
        $submissionCycle = (int) ($this->submission?->approval_cycle ?: 1);
        // Invalidated / cancelled peers stay on the current path (e.g. parallel "Not needed").
        // Only older cycles and full-restart supersessions belong in prior-cycle history.
        $isPriorCycle = $cycle < $submissionCycle
            || (string) $this->status === 'superseded';

        return [
            'id' => (string) $this->id,
            'status' => $displayStatus,
            'approval_status' => $this->status,
            'approval_cycle' => $cycle,
            'is_prior_cycle' => $isPriorCycle,
            'remarks' => $this->remarks,
            'signature' => $this->signature,
            'acted_at' => $this->acted_at?->toIso8601String(),
            'step_order' => $this->step?->step_order,
            'parallel_mode' => $this->parallelModeFromStep(),
            'parallel_quorum' => $this->parallelQuorumFromStep(),
            'approver' => $this->approver ? [
                'id' => (string) $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null,
            'submission' => $this->submission ? [
                'id' => (string) $this->submission->id,
                'document_no' => $this->submission->document_no,
                'status' => $this->submission->status,
                'form_name' => $this->submission->form?->name,
            ] : null,
        ];
    }

    private function parallelModeFromStep(): ?string
    {
        $condition = is_array($this->step?->condition) ? $this->step->condition : [];
        $mode = strtolower(trim((string) ($condition['parallel_mode'] ?? '')));

        return in_array($mode, ['any', 'n_of_m', 'all'], true) ? $mode : null;
    }

    private function parallelQuorumFromStep(): ?int
    {
        $condition = is_array($this->step?->condition) ? $this->step->condition : [];
        if (! is_numeric($condition['parallel_quorum'] ?? null)) {
            return null;
        }

        return max(1, (int) $condition['parallel_quorum']);
    }
}
