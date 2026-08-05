<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\Documents\Services\ControlledDocumentEApprovalHookService;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;
use App\Modules\EApproval\Support\EApprovalParallelMode;
use App\Modules\EApproval\Support\EApprovalSubmissionSource;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\EApproval\Support\EApprovalWorkflowConditionEvaluator;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SubmissionWorkflowService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalDocumentControlService $documentControl,
        private readonly EApprovalManagerApproverResolver $managerResolver,
        private readonly EApprovalRoleApproverResolver $roleResolver,
        private readonly EApprovalSubmissionWorkflowResolver $workflowResolver,
        private readonly EApprovalFieldMapResolver $fieldMapResolver,
        private readonly EApprovalFormFieldChoicesResolver $fieldChoicesResolver,
        private readonly ControlledDocumentEApprovalHookService $controlledDocumentHook,
        private readonly EApprovalWorkflowConditionEvaluator $conditionEvaluator,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<int, EApprovalWorkflowStep>|null  $stepsOverride
     */
    /**
     * @param  'initial'|'restart'  $notifyMode
     */
    public function initiateWorkflow(
        EApprovalSubmission $submission,
        EApprovalForm $form,
        array $values,
        ?Collection $stepsOverride = null,
        bool $preserveHistoricalApprovals = false,
        string $notifyMode = 'initial',
    ): void {
        if ($preserveHistoricalApprovals) {
            EApprovalRequestApproval::query()
                ->where('submission_id', $submission->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->update([
                    'status' => EApprovalApprovalStatus::SUPERSEDED,
                    'remarks' => __('Superseded by full workflow restart.'),
                    'acted_at' => now(),
                ]);
        } else {
            EApprovalRequestApproval::query()->where('submission_id', $submission->id)->delete();
        }

        if ($stepsOverride instanceof Collection && $stepsOverride->isNotEmpty()) {
            $steps = $stepsOverride->sortBy('step_order')->values();
        } else {
            $form->loadMissing('workflowTemplate.steps');
            $steps = $form->workflowTemplate?->steps ?? collect();
        }

        if ($steps->isEmpty()) {
            $submission->status = EApprovalSubmissionStatus::APPROVED;
            $submission->save();
            $this->audit->log('no_steps', $submission->id, 'No workflow steps; auto-approved.');
            $this->notifyRequestorOutcome($submission, 'approved', __('System'));
            $submission->loadMissing(['form', 'values.field', 'attachments']);
            $this->controlledDocumentHook->afterSubmissionMutation($submission, null);

            return;
        }

        $currentOrder = null;
        $activated = 0;
        $unresolvedSteps = [];
        $cycle = max(1, (int) ($submission->approval_cycle ?: 1));

        foreach ($steps as $step) {
            if ($currentOrder !== null && $step->step_order > $currentOrder) {
                break;
            }

            if (! $this->evaluateCondition($step->condition, $values)) {
                $this->audit->log('skip_step', $submission->id, "Condition not met for step {$step->step_order}");

                continue;
            }

            $approverId = $this->resolveApproverId($step, $values, $submission, $form);
            if ($approverId === null) {
                $unresolvedSteps[] = $this->describeUnresolvedStep($step);
                $this->audit->log('skip_step', $submission->id, "No approver for step {$step->step_order}");

                continue;
            }

            if ($currentOrder === null) {
                $currentOrder = $step->step_order;
            }

            EApprovalRequestApproval::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'step_id' => $step->id,
                'approver_id' => $approverId,
                'status' => EApprovalApprovalStatus::PENDING,
                'approval_cycle' => $cycle,
            ]);

            $activated++;
            $this->notifyApproverAssigned($submission, $approverId, revised: $notifyMode === 'restart');
        }

        if ($activated > 0) {
            $submission->status = EApprovalSubmissionStatus::PENDING;
            $submission->current_step = (int) $currentOrder;
            $submission->save();
            $this->audit->log('workflow_initiated', $submission->id, "Activated {$activated} step(s) at order {$currentOrder}");
            $this->notifyRequestorSubmitted($submission, $notifyMode === 'restart' ? 'restart' : 'initial');
        } else {
            throw ValidationException::withMessages([
                'workflow' => array_values(array_filter([
                    __('No approvers could be assigned for this submission. Review workflow steps and try again.'),
                    ...$unresolvedSteps,
                ])),
            ]);
        }
    }

    /**
     * Re-open pending approvals only at the given step order (revision resume).
     * Falls back by advancing past the step when its condition/approver no longer applies.
     *
     * @param  array<string, mixed>  $values
     * @param  Collection<int, EApprovalWorkflowStep>|null  $stepsOverride
     * @return array{routed: string, reason: string, current_step: int}
     */
    public function resumeWorkflowAtStep(
        EApprovalSubmission $submission,
        EApprovalForm $form,
        array $values,
        int $stepOrder,
        ?Collection $stepsOverride = null,
    ): array {
        EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->update([
                'status' => EApprovalApprovalStatus::INVALIDATED,
                'remarks' => __('Invalidated before resume.'),
                'acted_at' => now(),
            ]);

        if ($stepsOverride instanceof Collection && $stepsOverride->isNotEmpty()) {
            $steps = $stepsOverride->sortBy('step_order')->values();
        } else {
            $steps = $this->workflowResolver->stepsForAdvance($submission);
            if ($steps->isEmpty()) {
                $form->loadMissing('workflowTemplate.steps');
                $steps = $form->workflowTemplate?->steps?->sortBy('step_order')->values() ?? collect();
            }
        }

        $targetSteps = $steps->where('step_order', $stepOrder)->values();
        $cycle = max(1, (int) ($submission->approval_cycle ?: 1));
        $activated = 0;

        foreach ($targetSteps as $step) {
            if (! $this->evaluateCondition($step->condition, $values)) {
                continue;
            }

            $approverId = $this->resolveApproverId($step, $values, $submission, $form);
            if ($approverId === null) {
                continue;
            }

            EApprovalRequestApproval::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'step_id' => $step->id,
                'approver_id' => $approverId,
                'status' => EApprovalApprovalStatus::PENDING,
                'approval_cycle' => $cycle,
            ]);

            $activated++;
            $this->notifyApproverAssigned($submission, $approverId, revised: true);
        }

        if ($activated > 0) {
            $submission->status = EApprovalSubmissionStatus::PENDING;
            $submission->current_step = $stepOrder;
            $submission->save();
            $this->audit->log(
                'workflow_resumed',
                $submission->id,
                "Resumed at step order {$stepOrder} ({$activated} pending)",
            );
            $this->notifyRequestorSubmitted($submission, 'resume');

            return [
                'routed' => 'resume_returning_step',
                'reason' => 'resume_returning_step',
                'current_step' => $stepOrder,
            ];
        }

        // Condition/approver no longer valid at return step — try advancing past it.
        $submission->status = EApprovalSubmissionStatus::PENDING;
        $submission->current_step = $stepOrder;
        $submission->save();

        $hasMore = $this->triggerNextStep($submission, $stepOrder, revisedAssign: true);
        $submission->refresh();

        if ($hasMore || $submission->approvals()->where('status', EApprovalApprovalStatus::PENDING)->exists()) {
            $this->audit->log(
                'workflow_resumed_advanced',
                $submission->id,
                "Return step {$stepOrder} not applicable; advanced to step {$submission->current_step}",
            );
            $this->notifyRequestorSubmitted($submission, 'resume');

            return [
                'routed' => 'resume_returning_step',
                'reason' => 'return_step_condition_failed',
                'current_step' => (int) $submission->current_step,
            ];
        }

        throw ValidationException::withMessages([
            'workflow' => [__('Could not resume the workflow at the returned step. Resubmit will restart from step 1.')],
        ]);
    }

    /**
     * Whether at least one step at the given order would activate with the provided values.
     *
     * @param  array<string, mixed>  $values
     * @param  Collection<int, EApprovalWorkflowStep>|null  $steps
     */
    public function stepOrderIsActivatable(
        EApprovalSubmission $submission,
        EApprovalForm $form,
        array $values,
        int $stepOrder,
        ?Collection $steps = null,
    ): bool {
        $steps ??= $this->workflowResolver->stepsForAdvance($submission);
        if ($steps->isEmpty()) {
            $form->loadMissing('workflowTemplate.steps');
            $steps = $form->workflowTemplate?->steps?->sortBy('step_order')->values() ?? collect();
        }

        foreach ($steps->where('step_order', $stepOrder) as $step) {
            if (! $this->evaluateCondition($step->condition, $values)) {
                continue;
            }
            if ($this->resolveApproverId($step, $values, $submission, $form) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  'initial'|'resume'|'restart'  $mode
     */
    private function notifyRequestorSubmitted(EApprovalSubmission $submission, string $mode = 'initial'): void
    {
        $requestorId = (string) $submission->requestor_id;
        if ($requestorId === '') {
            return;
        }

        $isExternal = ($submission->submission_source ?? EApprovalSubmissionSource::INTERNAL) === EApprovalSubmissionSource::EXTERNAL;

        if ($isExternal) {
            $this->mail->dispatchToRequestor($submission, 'external_received');

            return;
        }

        $documentNo = (string) $submission->document_no;
        $step = max(1, (int) ($submission->current_step ?: 1));

        [$event, $message] = match ($mode) {
            'resume' => [
                'resubmitted_resume',
                __('Your revised request :doc was resubmitted. Approval resumed at step :step.', [
                    'doc' => $documentNo,
                    'step' => $step,
                ]),
            ],
            'restart' => [
                'resubmitted_restart',
                __('Your revised request :doc was resubmitted. The workflow restarted from step 1.', [
                    'doc' => $documentNo,
                ]),
            ],
            default => [
                'submitted',
                __('Your request :doc was submitted and is pending approval.', ['doc' => $documentNo]),
            ],
        };

        $this->inApp->notify(
            $requestorId,
            $event,
            $submission->id,
            $message,
            submission: $submission,
        );
        $this->mail->dispatchToRequestor($submission, $event);
    }

    private function notifyApproverAssigned(
        EApprovalSubmission $submission,
        string $approverId,
        bool $revised = false,
    ): void {
        $event = $revised ? 'approval_assigned_revised' : 'approval_assigned';
        $message = $revised
            ? __('You have a revised approval request for :doc.', ['doc' => $submission->document_no])
            : __('You have a new approval request for :doc.', ['doc' => $submission->document_no]);

        $this->inApp->notify(
            $approverId,
            $event,
            $submission->id,
            $message,
            submission: $submission,
        );
        $this->mail->dispatchApprovalAssigned($submission, $approverId, $revised);
    }

    /**
     * Notify requestor when exclusive-band / condition-gated steps were omitted from the compiled path.
     *
     * @param  list<array<string, mixed>>  $skippedSteps
     */
    public function notifyCompiledExclusiveSkips(EApprovalSubmission $submission, array $skippedSteps): void
    {
        $orders = [];
        foreach ($skippedSteps as $step) {
            if (! is_array($step)) {
                continue;
            }
            $order = (int) ($step['step_order'] ?? 0);
            if ($order > 0) {
                $orders[] = $order;
            }
        }

        $this->notifyRequestorStepsSkipped(
            $submission,
            $orders,
            max(1, (int) ($submission->current_step ?: 1)),
        );
    }

    /**
     * @param  list<int>  $skippedOrders
     */
    private function notifyRequestorStepsSkipped(
        EApprovalSubmission $submission,
        array $skippedOrders,
        int $nowAtStep,
    ): void {
        $skippedOrders = array_values(array_unique(array_filter(
            $skippedOrders,
            static fn (int $order): bool => $order > 0,
        )));
        sort($skippedOrders);

        if ($skippedOrders === []) {
            return;
        }

        $requestorId = (string) $submission->requestor_id;
        if ($requestorId === '') {
            return;
        }

        $stepsLabel = implode(', ', array_map(static fn (int $order): string => (string) $order, $skippedOrders));
        $detail = __('Skipped step(s) :steps. Now awaiting step :current.', [
            'steps' => $stepsLabel,
            'current' => $nowAtStep,
        ]);

        $this->inApp->notify(
            $requestorId,
            'workflow_steps_skipped',
            $submission->id,
            __('Workflow path updated for :doc. :detail', [
                'doc' => $submission->document_no,
                'detail' => $detail,
            ]),
            submission: $submission,
        );
        $this->mail->dispatchWorkflowStepsSkipped($submission, $detail);
    }

    private function notifyRequestorOutcome(EApprovalSubmission $submission, string $event, ?string $actorName): void
    {
        $requestorId = (string) $submission->requestor_id;
        if ($requestorId === '') {
            return;
        }

        $documentNo = (string) $submission->document_no;
        $message = $event === 'approved'
            ? __('Your request :doc was approved.', ['doc' => $documentNo])
            : __('Your request :doc was updated.', ['doc' => $documentNo]);

        $this->inApp->notify(
            $requestorId,
            $event,
            $submission->id,
            $message,
            submission: $submission,
        );
        $this->mail->dispatchToRequestor($submission, $event, $actorName);
    }

    /**
     * @param  list<int>  $conditionSkippedOrders
     */
    public function triggerNextStep(
        EApprovalSubmission $submission,
        int $completedStepOrder,
        bool $revisedAssign = false,
        array $conditionSkippedOrders = [],
    ): bool {
        $this->settleParallelBandIfQuorumMet($submission, $completedStepOrder);

        $workflowSteps = $this->workflowResolver->stepsForAdvance($submission);
        $activeStepIds = $workflowSteps
            ->map(static fn (EApprovalWorkflowStep $step): string => (string) $step->id)
            ->all();
        $cycle = max(1, (int) ($submission->approval_cycle ?: 1));

        // Only pending rows on the current compile count — orphan compiled steps from a
        // prior resubmit must not block advancement.
        $pendingSameOrderQuery = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->where(function ($query) use ($cycle): void {
                $query->where('approval_cycle', $cycle)->orWhereNull('approval_cycle');
            })
            ->whereHas('step', static fn ($q) => $q->where('step_order', $completedStepOrder));

        if ($activeStepIds !== []) {
            $pendingSameOrderQuery->whereIn('step_id', $activeStepIds);
        }

        if ($pendingSameOrderQuery->exists()) {
            return true;
        }

        // Heal stuck submissions created before snapshot-scoped advance: orphan
        // pending rows on prior compiled steps must not remain actionable.
        if ($activeStepIds !== []) {
            EApprovalRequestApproval::query()
                ->where('submission_id', $submission->id)
                ->where('status', EApprovalApprovalStatus::PENDING)
                ->where(function ($query) use ($cycle): void {
                    $query->where('approval_cycle', $cycle)->orWhereNull('approval_cycle');
                })
                ->whereHas('step', static fn ($q) => $q->where('step_order', $completedStepOrder))
                ->whereNotIn('step_id', $activeStepIds)
                ->update([
                    'status' => EApprovalApprovalStatus::INVALIDATED,
                    'remarks' => __('Cleared stale approval from a prior workflow compile.'),
                    'acted_at' => now(),
                ]);
        }

        if ($this->documentControl->tryEnterGate($submission, $completedStepOrder)) {
            return true;
        }

        $submission->loadMissing(['form.workflowTemplate.steps', 'values.field']);
        $values = $this->valuesMap($submission);
        $nextSteps = $workflowSteps
            ->where('step_order', '>', $completedStepOrder)
            ->sortBy('step_order')
            ->groupBy('step_order')
            ->first();

        if ($nextSteps === null || $nextSteps->isEmpty()) {
            return false;
        }

        $nextOrder = (int) $nextSteps->first()->step_order;
        $activated = 0;
        $conditionSkipsInBand = 0;

        foreach ($nextSteps as $step) {
            if ($step->step_order !== $nextOrder) {
                break;
            }

            if (! $this->evaluateCondition($step->condition, $values)) {
                $conditionSkipsInBand++;
                $this->audit->log('skip_step', $submission->id, "Condition not met for step {$step->step_order}");

                continue;
            }

            $approverId = $this->resolveApproverId($step, $values, $submission, $submission->form);
            if ($approverId === null) {
                $message = $this->describeUnresolvedStep($step);
                $this->audit->log('skip_step', $submission->id, $message);

                continue;
            }

            EApprovalRequestApproval::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'step_id' => $step->id,
                'approver_id' => $approverId,
                'status' => EApprovalApprovalStatus::PENDING,
                'approval_cycle' => $cycle,
            ]);

            $activated++;
            $this->notifyApproverAssigned($submission, $approverId, revised: $revisedAssign);
        }

        if ($activated > 0) {
            $submission->current_step = $nextOrder;
            $submission->status = EApprovalSubmissionStatus::PENDING;
            $submission->save();
            $this->notifyRequestorStepsSkipped($submission, $conditionSkippedOrders, $nextOrder);

            return true;
        }

        if ($conditionSkipsInBand > 0) {
            $conditionSkippedOrders[] = $nextOrder;
            $conditionSkippedOrders = array_values(array_unique($conditionSkippedOrders));
        }

        return $this->triggerNextStep(
            $submission,
            $nextOrder,
            revisedAssign: $revisedAssign,
            conditionSkippedOrders: $conditionSkippedOrders,
        );
    }

    /**
     * For parallel bands configured as any / n_of_m, invalidate remaining pending
     * siblings once the required number of approvals is reached.
     */
    public function settleParallelBandIfQuorumMet(EApprovalSubmission $submission, int $stepOrder): void
    {
        $cycle = max(1, (int) ($submission->approval_cycle ?: 1));
        $activeStepIds = $this->workflowResolver->currentCompiledStepIds($submission);

        $bandApprovalsQuery = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->where(function ($query) use ($cycle): void {
                $query->where('approval_cycle', $cycle)->orWhereNull('approval_cycle');
            })
            ->whereHas('step', static fn ($q) => $q->where('step_order', $stepOrder))
            ->with('step');

        if ($activeStepIds !== []) {
            $bandApprovalsQuery->whereIn('step_id', $activeStepIds);
        }

        $bandApprovals = $bandApprovalsQuery->get();

        if ($bandApprovals->count() < 2) {
            return;
        }

        // Ignore already-cleared rows from prior settles when sizing the band.
        $activeBand = $bandApprovals->filter(
            static fn (EApprovalRequestApproval $row): bool => in_array(
                (string) $row->status,
                [EApprovalApprovalStatus::PENDING, EApprovalApprovalStatus::APPROVED],
                true,
            ),
        );
        if ($activeBand->count() < 2 && $bandApprovals->where('status', EApprovalApprovalStatus::APPROVED)->count() < 1) {
            return;
        }

        $sampleStep = $bandApprovals->first(
            static fn (EApprovalRequestApproval $row) => $row->step !== null,
        )?->step;
        $condition = is_array($sampleStep?->condition) ? $sampleStep->condition : [];
        $mode = EApprovalParallelMode::fromCondition($condition);
        if ($mode === EApprovalParallelMode::ALL) {
            return;
        }

        $memberCount = $bandApprovals
            ->reject(static fn (EApprovalRequestApproval $row): bool => in_array(
                (string) $row->status,
                [EApprovalApprovalStatus::INVALIDATED, EApprovalApprovalStatus::SUPERSEDED, EApprovalApprovalStatus::CANCELLED],
                true,
            ))
            ->count();
        if ($memberCount < 1) {
            $memberCount = $bandApprovals->count();
        }

        $quorum = EApprovalParallelMode::quorumFromCondition($condition, $memberCount);
        $approvedCount = $bandApprovals
            ->where('status', EApprovalApprovalStatus::APPROVED)
            ->count();

        if ($approvedCount < $quorum) {
            return;
        }

        $pendingRows = $bandApprovals->where('status', EApprovalApprovalStatus::PENDING)->values();
        $pendingIds = $pendingRows->pluck('id')->all();

        if ($pendingIds === []) {
            return;
        }

        $clearedApproverIds = $pendingRows
            ->pluck('approver_id')
            ->map(static fn ($id) => (string) $id)
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values()
            ->all();

        EApprovalRequestApproval::query()
            ->whereIn('id', $pendingIds)
            ->update([
                'status' => EApprovalApprovalStatus::INVALIDATED,
                'remarks' => $mode === EApprovalParallelMode::ANY
                    ? __('Invalidated — another approver already satisfied this parallel step.')
                    : __('Invalidated — parallel quorum already met.'),
                'acted_at' => now(),
            ]);

        $this->audit->log(
            'parallel_quorum_met',
            $submission->id,
            "Step {$stepOrder} parallel mode {$mode} met quorum {$quorum}/{$memberCount}; invalidated ".count($pendingIds).' pending approval(s).',
        );

        foreach ($clearedApproverIds as $approverId) {
            $this->inApp->notify(
                $approverId,
                'approval_no_longer_needed',
                (string) $submission->id,
                __('Your approval for :doc is no longer needed — another approver completed this parallel step.', [
                    'doc' => $submission->document_no,
                ]),
                submission: $submission,
            );
            $this->mail->dispatchApprovalNoLongerNeeded($submission, $approverId);
        }
    }

    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $values
     */
    public function evaluateCondition(?array $condition, array $values): bool
    {
        return $this->conditionEvaluator->matchesStoredCondition($condition, $values);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveApproverId(
        EApprovalWorkflowStep $step,
        array $values,
        EApprovalSubmission $submission,
        ?EApprovalForm $form = null,
    ): ?string {
        $primary = $this->resolvePrimaryApproverId($step, $values, $submission, $form);
        if ($primary !== null) {
            return $primary;
        }

        $fallback = $this->resolveFallbackApproverId($step);
        if ($fallback !== null) {
            $this->audit->log(
                'fallback_approver',
                $submission->id,
                "Step {$step->step_order} used fallback approver after primary resolution failed.",
            );
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolvePrimaryApproverId(
        EApprovalWorkflowStep $step,
        array $values,
        EApprovalSubmission $submission,
        ?EApprovalForm $form = null,
    ): ?string {
        $approverType = $this->normalizeApproverType((string) $step->approver_type);
        $approverId = $step->approver_id;

        if ($approverType === 'field' && $step->approver_id) {
            $candidate = trim((string) ($values[$step->approver_id] ?? ''));
            if ($candidate === '') {
                return null;
            }
            $approverId = $candidate;
        } elseif ($approverType === 'manager') {
            return $this->managerResolver->resolveForSubmission($submission);
        } elseif ($approverType === 'role' && $step->approver_id) {
            return $this->roleResolver->resolveFirstApproverForRole((string) $step->approver_id);
        } elseif ($approverType === 'field_map') {
            return $this->resolveFieldMapApproverId($step, $values, $form);
        } elseif ($approverType !== 'user') {
            return null;
        }

        return $this->resolveActiveUserId($approverId);
    }

    private function resolveFallbackApproverId(EApprovalWorkflowStep $step): ?string
    {
        $condition = is_array($step->condition) ? $step->condition : [];
        $fallback = $condition['fallback_approver_id'] ?? null;
        if (! is_string($fallback) || trim($fallback) === '') {
            return null;
        }

        return $this->resolveActiveUserId(trim($fallback));
    }

    private function resolveActiveUserId(mixed $approverId): ?string
    {
        if ($approverId === null || trim((string) $approverId) === '') {
            return null;
        }

        $approverId = trim((string) $approverId);

        $userId = TenantUser::query()
            ->where('id', $approverId)
            ->where('is_active', true)
            ->value('id');

        if ($userId !== null) {
            return (string) $userId;
        }

        if (str_contains($approverId, '@')) {
            $byEmail = TenantUser::query()
                ->whereRaw('LOWER(email) = ?', [strtolower($approverId)])
                ->where('is_active', true)
                ->value('id');

            return $byEmail !== null ? (string) $byEmail : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveFieldMapApproverId(EApprovalWorkflowStep $step, array $values, ?EApprovalForm $form = null): ?string
    {
        $sourceField = trim((string) ($step->approver_id ?? ''));
        if ($sourceField === '') {
            return null;
        }

        $raw = trim((string) ($values[$sourceField] ?? ''));
        $condition = is_array($step->condition) ? $step->condition : [];
        $mappings = is_array($condition['mappings'] ?? null) ? $condition['mappings'] : [];
        $default = $condition['default_approver_id'] ?? null;
        $choices = $form !== null ? $this->fieldChoicesResolver->choicesForFieldName($form, $sourceField) : [];

        return $this->fieldMapResolver->resolveApproverId(
            $mappings,
            $raw,
            is_string($default) ? $default : null,
            $choices,
        );
    }

    private function normalizeApproverType(string $type): string
    {
        return EApprovalFormPolicySupport::normalizeApproverType($type);
    }

    private function describeUnresolvedStep(EApprovalWorkflowStep $step): string
    {
        $order = (int) $step->step_order;
        $type = $this->normalizeApproverType((string) $step->approver_type);

        return match ($type) {
            'manager' => __('Step :order: direct manager could not be resolved from Entra ID.', ['order' => $order]),
            'field' => $step->approver_id
                ? __('Step :order: approver field ":field" is empty or invalid.', ['order' => $order, 'field' => $step->approver_id])
                : __('Step :order: "From approver field" step is missing a field mapping.', ['order' => $order]),
            'user_list' => $step->approver_id
                ? __('Step :order: approver list ":field" is empty or invalid.', ['order' => $order, 'field' => $step->approver_id])
                : __('Step :order: dynamic approver list step is missing a field mapping.', ['order' => $order]),
            'field_map' => __('Step :order: no approver mapping found for the selected field value.', ['order' => $order]),
            'role' => __('Step :order: no active user found for role.', ['order' => $order]),
            default => __('Step :order: fixed approver is missing or inactive.', ['order' => $order]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function valuesMap(EApprovalSubmission $submission): array
    {
        $map = [];
        foreach ($submission->values as $row) {
            $key = $row->field?->name ?? (string) $row->field_id;
            $map[$key] = $row->value;
        }

        return $map;
    }
}
