<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;
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
     * Compiled rows can miss a checkbox saved on the form after the request started.
     * The live step at the same order still counts.
     */
    public function requiresProofOfPayment(): bool
    {
        $condition = is_array($this->step?->condition) ? $this->step->condition : [];
        if (! empty($condition['proof_of_payment'])) {
            return true;
        }

        $order = (int) ($this->step?->step_order ?? 0);
        if ($order < 1) {
            return false;
        }

        $this->loadMissing('submission.form.workflowTemplate.steps');
        $templateSteps = $this->submission?->form?->workflowTemplate?->steps;

        return $templateSteps !== null && $templateSteps->contains(
            static fn (EApprovalWorkflowStep $step): bool => (int) $step->step_order === $order
                && is_array($step->condition)
                && ! empty($step->condition['proof_of_payment']),
        );
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
            'finance_action' => is_array($this->step?->condition)
                ? ($this->step->condition['finance_action'] ?? null)
                : null,
            'proof_of_payment' => $this->requiresProofOfPayment(),
            'decision_labels' => EApprovalWorkflowStepDefinitionSupport::decisionLabels(
                is_array($this->step?->condition) ? ['condition' => $this->step->condition] : [],
            ),
            'parallel_mode' => $this->parallelModeFromStep(),
            'parallel_quorum' => $this->parallelQuorumFromStep(),
            'duty' => is_array($this->step?->condition) && ($this->step->condition['duty'] ?? null) === 'review'
                ? 'review'
                : null,
            'approver' => $this->approver ? [
                'id' => (string) $this->approver->id,
                'name' => $this->approver->name,
                'email' => $this->approver->email,
            ] : null,
            'submission' => $this->submission ? $this->submissionSummary() : null,
        ];
    }

    /**
     * Same card fields the submissions list shows.
     *
     * @return array<string, mixed>
     */
    private function submissionSummary(): array
    {
        $submission = $this->submission;
        if ($submission === null) {
            return [];
        }

        $list = $submission->toListRow();

        return [
            'id' => $list['id'],
            'document_no' => $list['document_no'],
            'status' => $list['status'],
            'status_label' => $list['status_label'] ?? null,
            'form_id' => $list['form_id'] ?? null,
            'form_name' => $list['form_name'] ?? null,
            'subsidiary' => $list['subsidiary'] ?? null,
            'current_step' => $list['current_step'] ?? 0,
            'step_count' => $list['step_count'] ?? null,
            'workflow_steps' => $list['workflow_steps'] ?? [],
            'requestor' => $list['requestor'] ?? null,
            'created_at' => $list['created_at'] ?? null,
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
