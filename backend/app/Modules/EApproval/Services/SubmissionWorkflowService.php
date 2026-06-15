<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalApprovalStatus;
use App\Modules\EApproval\Support\EApprovalSubmissionSource;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Str;

final class SubmissionWorkflowService
{
    public function __construct(
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalInAppNotificationService $inApp,
        private readonly EApprovalNotificationDispatcher $mail,
        private readonly EApprovalDocumentControlService $documentControl,
        private readonly EApprovalManagerApproverResolver $managerResolver,
        private readonly EApprovalSubmissionWorkflowResolver $workflowResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function initiateWorkflow(EApprovalSubmission $submission, EApprovalForm $form, array $values): void
    {
        EApprovalRequestApproval::query()->where('submission_id', $submission->id)->delete();

        $form->loadMissing('workflowTemplate.steps');
        $steps = $form->workflowTemplate?->steps ?? collect();

        if ($steps->isEmpty()) {
            $submission->status = EApprovalSubmissionStatus::APPROVED;
            $submission->save();
            $this->audit->log('no_steps', $submission->id, 'No workflow steps; auto-approved.');
            $this->notifyRequestorOutcome($submission, 'approved', __('System'));

            return;
        }

        $currentOrder = null;
        $activated = 0;

        foreach ($steps as $step) {
            if ($currentOrder !== null && $step->step_order > $currentOrder) {
                break;
            }

            if (! $this->evaluateCondition($step->condition, $values)) {
                $this->audit->log('skip_step', $submission->id, "Condition not met for step {$step->step_order}");

                continue;
            }

            $approverId = $this->resolveApproverId($step, $values, $submission);
            if ($approverId === null) {
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
            ]);

            $activated++;
            $this->inApp->notify(
                $approverId,
                'approval_assigned',
                $submission->id,
                __('You have a new approval request for :doc.', ['doc' => $submission->document_no]),
                submission: $submission,
            );
            $this->mail->dispatchApprovalAssigned($submission, $approverId);
        }

        if ($activated > 0) {
            $submission->status = EApprovalSubmissionStatus::PENDING;
            $submission->current_step = (int) $currentOrder;
            $submission->save();
            $this->audit->log('workflow_initiated', $submission->id, "Activated {$activated} step(s) at order {$currentOrder}");
            $this->notifyRequestorSubmitted($submission);
        } else {
            $submission->status = EApprovalSubmissionStatus::APPROVED;
            $submission->save();
            $this->audit->log('no_steps_match', $submission->id, 'No matching steps; auto-approved.');
            $this->notifyRequestorOutcome($submission, 'approved', __('System'));
        }
    }

    private function notifyRequestorSubmitted(EApprovalSubmission $submission): void
    {
        $requestorId = (string) $submission->requestor_id;
        if ($requestorId === '') {
            return;
        }

        $isExternal = ($submission->submission_source ?? EApprovalSubmissionSource::INTERNAL) === EApprovalSubmissionSource::EXTERNAL;

        if (! $isExternal) {
            $this->inApp->notify(
                $requestorId,
                'submitted',
                $submission->id,
                __('Your request :doc was submitted and is pending approval.', ['doc' => $submission->document_no]),
                submission: $submission,
            );
            $this->mail->dispatchToRequestor($submission, 'submitted');
        } else {
            $this->mail->dispatchToRequestor($submission, 'external_received');
        }
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

    public function triggerNextStep(EApprovalSubmission $submission, int $completedStepOrder): bool
    {
        $pendingSameOrder = EApprovalRequestApproval::query()
            ->where('submission_id', $submission->id)
            ->whereHas('step', static fn ($q) => $q->where('step_order', $completedStepOrder))
            ->where('status', EApprovalApprovalStatus::PENDING)
            ->exists();

        if ($pendingSameOrder) {
            return true;
        }

        if ($this->documentControl->tryEnterGate($submission, $completedStepOrder)) {
            return true;
        }

        $submission->loadMissing(['form.workflowTemplate.steps', 'values.field']);
        $values = $this->valuesMap($submission);

        $workflowSteps = $this->workflowResolver->stepsForAdvance($submission);
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

        foreach ($nextSteps as $step) {
            if ($step->step_order !== $nextOrder) {
                break;
            }

            if (! $this->evaluateCondition($step->condition, $values)) {
                continue;
            }

            $approverId = $this->resolveApproverId($step, $values, $submission);
            if ($approverId === null) {
                continue;
            }

            EApprovalRequestApproval::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'step_id' => $step->id,
                'approver_id' => $approverId,
                'status' => EApprovalApprovalStatus::PENDING,
            ]);

            $activated++;
            $this->inApp->notify(
                $approverId,
                'approval_assigned',
                $submission->id,
                __('You have a new approval request for :doc.', ['doc' => $submission->document_no]),
                submission: $submission,
            );
            $this->mail->dispatchApprovalAssigned($submission, $approverId);
        }

        if ($activated > 0) {
            $submission->current_step = $nextOrder;
            $submission->status = EApprovalSubmissionStatus::PENDING;
            $submission->save();

            return true;
        }

        return $this->triggerNextStep($submission, $nextOrder);
    }

    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $values
     */
    public function evaluateCondition(?array $condition, array $values): bool
    {
        if ($condition === null || empty($condition['field'])) {
            return true;
        }

        $field = (string) $condition['field'];
        $operator = (string) ($condition['operator'] ?? '==');
        $expected = (string) ($condition['value'] ?? '');
        $actual = (string) ($values[$field] ?? '');

        return match ($operator) {
            '>' => is_numeric($actual) && is_numeric($expected) && (float) $actual > (float) $expected,
            '<' => is_numeric($actual) && is_numeric($expected) && (float) $actual < (float) $expected,
            '>=' => is_numeric($actual) && is_numeric($expected) && (float) $actual >= (float) $expected,
            '<=' => is_numeric($actual) && is_numeric($expected) && (float) $actual <= (float) $expected,
            '!=' => $actual !== $expected,
            default => $actual === $expected,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function resolveApproverId(EApprovalWorkflowStep $step, array $values, EApprovalSubmission $submission): ?string
    {
        $approverId = $step->approver_id;

        if ($step->approver_type === 'field' && $step->approver_id) {
            $candidate = trim((string) ($values[$step->approver_id] ?? ''));
            if ($candidate === '') {
                return null;
            }
            $approverId = $candidate;
        } elseif ($step->approver_type === 'manager') {
            return $this->managerResolver->resolveForSubmission($submission);
        }

        if ($approverId === null || $approverId === '') {
            return null;
        }

        return TenantUser::query()->where('id', $approverId)->value('id');
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
