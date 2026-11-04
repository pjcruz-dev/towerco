<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Models;

use App\Modules\EApproval\Support\EApprovalLiveStepMatcher;
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
     * The checkbox on the matching live step wins. A later copy of the same person
     * is a different step. A payment already released on this request is not asked for again.
     */
    public function requiresProofOfPayment(): bool
    {
        if ($this->latestPaymentProofStatus() === 'released') {
            return false;
        }

        $live = $this->matchingLiveTemplateStep();
        if ($live !== null) {
            $condition = is_array($live->condition) ? $live->condition : [];

            return ! empty($condition['proof_of_payment']);
        }

        $condition = is_array($this->step?->condition) ? $this->step->condition : [];

        return ! empty($condition['proof_of_payment']);
    }

    /**
     * Compiled rows can miss a checkbox saved on the form after the request started.
     * Match the same person and the same when-clause. Runtime step numbers are compacted,
     * so the template step at the same number can be a different branch.
     */
    public function requiresSettleCash(): bool
    {
        $condition = is_array($this->step?->condition) ? $this->step->condition : [];
        if (! empty($condition['confirm_settlement'])) {
            return true;
        }

        return $this->liveTemplateHasFlag('confirm_settlement');
    }

    public function requiresReturnToStep(): bool
    {
        $live = $this->matchingLiveTemplateStep();
        $source = $live ?? $this->step;
        $condition = is_array($source?->condition) ? $source->condition : [];

        return ! empty($condition['return_to_step']);
    }

    /**
     * A chosen earlier step, or null when the switch means the previous step.
     */
    public function configuredReturnStepOrder(): ?int
    {
        if (! $this->requiresReturnToStep()) {
            return null;
        }

        $live = $this->matchingLiveTemplateStep();
        $source = $live ?? $this->step;
        $condition = is_array($source?->condition) ? $source->condition : [];
        $raw = $condition['return_to_step_order'] ?? null;

        return is_numeric($raw) && (int) $raw > 0 ? (int) $raw : null;
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
            'confirm_settlement' => $this->requiresSettleCash(),
            'return_to_step' => $this->requiresReturnToStep(),
            'return_to_step_target' => $this->requiresReturnToStep() ? $this->resolvedReturnStepOrder() : null,
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

    private function latestPaymentProofStatus(): ?string
    {
        $finance = is_array($this->submission?->finance_json) ? $this->submission->finance_json : [];
        $entries = is_array($finance['payment_proofs'] ?? null) ? $finance['payment_proofs'] : [];
        $latest = null;
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $status = strtolower(trim((string) ($entry['status'] ?? '')));
            if (in_array($status, ['released', 'hold'], true)) {
                $latest = $status;
            }
        }

        return $latest;
    }

    /**
     * The live template step for this person, not whichever template row shares the compacted order.
     */
    private function resolvedReturnStepOrder(): ?int
    {
        $current = (int) ($this->step?->step_order ?? $this->submission?->current_step ?? 0);
        $configured = $this->configuredReturnStepOrder();
        if ($configured !== null && $configured < $current) {
            return $configured;
        }

        $this->loadMissing('submission.approvals.step');
        $orders = $this->submission?->approvals
            ?->map(static fn (self $row): int => (int) ($row->step?->step_order ?? 0))
            ->filter(static fn (int $order): bool => $order > 0 && $order < $current)
            ->unique()
            ->sort()
            ->values();

        return $orders !== null && $orders->isNotEmpty() ? (int) $orders->last() : null;
    }

    private function matchingLiveTemplateStep(): ?EApprovalWorkflowStep
    {
        $this->loadMissing('submission');
        if ($this->submission === null) {
            return null;
        }

        return EApprovalLiveStepMatcher::match($this->submission, $this->step);
    }

    private function liveTemplateHasFlag(string $flag): bool
    {
        $compiled = $this->step;
        if ($compiled === null) {
            return false;
        }

        $this->loadMissing('submission.form.workflowTemplate.steps');
        $templateSteps = $this->submission?->form?->workflowTemplate?->steps;
        if ($templateSteps === null) {
            return false;
        }

        $whenKey = EApprovalLiveStepMatcher::whenKey($compiled);
        $approverId = (string) ($compiled->approver_id ?? '');
        $type = (string) $compiled->approver_type;

        return $templateSteps->contains(function (EApprovalWorkflowStep $step) use ($flag, $whenKey, $approverId, $type): bool {
            $condition = is_array($step->condition) ? $step->condition : [];
            if (empty($condition[$flag])) {
                return false;
            }
            if (EApprovalLiveStepMatcher::whenKey($step) !== $whenKey) {
                return false;
            }
            if ($approverId !== '' && (string) ($step->approver_id ?? '') === $approverId) {
                return true;
            }
            $fixed = $condition['fixed_approver_ids'] ?? [];
            $fixedIds = [];
            if (is_array($fixed)) {
                foreach ($fixed as $id) {
                    if (is_scalar($id) && trim((string) $id) !== '') {
                        $fixedIds[] = trim((string) $id);
                    }
                }
            }
            if ($approverId !== '' && in_array($approverId, $fixedIds, true)) {
                return true;
            }

            return $approverId === '' && (string) $step->approver_type === $type;
        });
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
