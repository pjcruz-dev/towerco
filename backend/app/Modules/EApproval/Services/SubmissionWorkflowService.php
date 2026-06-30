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
use App\Modules\EApproval\Support\EApprovalSubmissionSource;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
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
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @param  Collection<int, EApprovalWorkflowStep>|null  $stepsOverride
     */
    public function initiateWorkflow(
        EApprovalSubmission $submission,
        EApprovalForm $form,
        array $values,
        ?Collection $stepsOverride = null,
    ): void {
        EApprovalRequestApproval::query()->where('submission_id', $submission->id)->delete();

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
            throw ValidationException::withMessages([
                'workflow' => array_values(array_filter([
                    __('No approvers could be assigned for this submission. Review workflow steps and try again.'),
                    ...$unresolvedSteps,
                ])),
            ]);
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

            $approverId = $this->resolveApproverId($step, $values, $submission, $submission->form);
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
    private function resolveApproverId(
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
