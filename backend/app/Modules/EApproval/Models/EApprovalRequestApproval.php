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
        'remarks',
        'acted_at',
        'signature',
        'last_reminder_at',
        'escalated_at',
    ];

    protected function casts(): array
    {
        return [
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
        $displayStatus = $this->status === 'pending'
            ? $this->status
            : ($submissionStatus ?? $this->status);

        return [
            'id' => (string) $this->id,
            'status' => $displayStatus,
            'approval_status' => $this->status,
            'remarks' => $this->remarks,
            'signature' => $this->signature,
            'acted_at' => $this->acted_at?->toIso8601String(),
            'step_order' => $this->step?->step_order,
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
}
