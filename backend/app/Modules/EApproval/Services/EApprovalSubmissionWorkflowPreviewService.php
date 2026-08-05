<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;

/**
 * Admin path preview for an existing submission using frozen snapshot definitions when present.
 */
final class EApprovalSubmissionWorkflowPreviewService
{
    public function __construct(
        private readonly EApprovalConditionalWorkflowCompilerService $compiler,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function preview(EApprovalSubmission $submission): array
    {
        $submission->loadMissing([
            'form.fields',
            'form.workflowTemplate.steps',
            'values.field',
            'requestor:id,name,email',
            'approvals.step',
            'approvals.approver:id,name,email',
        ]);

        $form = $submission->form;
        if ($form === null) {
            return [
                'workflow_mode' => 'unknown',
                'matched_rule_id' => null,
                'matched_rule_label' => null,
                'definition_source' => 'none',
                'resolved_steps' => [],
                'skipped_steps' => [],
            ];
        }

        $snapshot = $this->decodeSnapshot($submission);
        $values = $this->resolveValues($submission, $snapshot);
        $definitions = $this->resolveDefinitions($form, $snapshot);
        $definitionSource = $definitions['source'];

        $preview = $this->compiler->preview(
            $form,
            $values,
            is_string($submission->requestor?->email) ? $submission->requestor->email : null,
            $definitions['definitions'],
        );

        $activeStepIds = array_fill_keys(
            app(EApprovalSubmissionWorkflowResolver::class)->currentCompiledStepIds($submission),
            true,
        );
        $approvalsByStep = $this->currentCycleApprovalsByStep($submission, $activeStepIds);
        $returnedFromStep = (int) ($submission->returned_from_step ?? 0);
        $isReturned = (string) $submission->status === 'returned';
        $submissionStatus = (string) $submission->status;
        $currentStep = (int) ($submission->current_step ?? 0);
        $isTerminal = in_array($submissionStatus, ['approved', 'rejected', 'cancelled'], true);

        $resolved = [];
        foreach ($preview['resolved_steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $order = (int) ($step['step_order'] ?? 0);
            $userId = isset($step['resolved_user_id']) ? (string) $step['resolved_user_id'] : '';
            $match = $this->matchApproval($approvalsByStep[$order] ?? [], $userId);
            $runtimeStatus = $match?->status;
            // Revision clears the return step as invalidated — show Returned, not Not needed.
            if (
                $isReturned
                && $returnedFromStep > 0
                && $order === $returnedFromStep
                && $runtimeStatus === 'invalidated'
            ) {
                $runtimeStatus = 'returned';
            }

            $pathReason = __('Conditions matched — step runs for this submission.');
            $warning = $step['warning'] ?? null;

            // Engine skips steps with empty/unresolved approvers. Do not show those as
            // "Not started" with form-builder "preview sample" copy on a live submission.
            if (
                $match === null
                && $userId === ''
                && $this->shouldShowUnresolvedStepAsSkipped($order, $currentStep, $isTerminal, $step)
            ) {
                $runtimeStatus = 'skipped';
                $pathReason = $this->unresolvedSkipReason($step);
                $warning = null;
            }

            $resolved[] = [
                ...$step,
                'warning' => $warning,
                'path_reason' => $pathReason,
                'runtime_status' => $runtimeStatus,
                'approval_id' => $match?->id !== null ? (string) $match->id : null,
                'acted_at' => $match?->acted_at?->toIso8601String(),
                'signature' => is_string($match?->signature) && $match->signature !== ''
                    ? $match->signature
                    : null,
                'runtime_approver' => $match?->approver ? [
                    'id' => (string) $match->approver->id,
                    'name' => $match->approver->name,
                    'email' => $match->approver->email,
                ] : null,
            ];
        }

        $skipped = [];
        foreach ($preview['skipped_steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $skipped[] = [
                ...$step,
                'path_reason' => __('Skipped — conditions did not match this submission.'),
            ];
        }

        return [
            'workflow_mode' => $preview['workflow_mode'] ?? 'conditional_steps',
            'matched_rule_id' => $preview['matched_rule_id'] ?? null,
            'matched_rule_label' => $preview['matched_rule_label'] ?? null,
            'definition_source' => $definitionSource,
            'resolved_steps' => $resolved,
            'skipped_steps' => $skipped,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSnapshot(EApprovalSubmission $submission): array
    {
        $raw = $submission->workflow_snapshot_json;
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function resolveValues(EApprovalSubmission $submission, array $snapshot): array
    {
        $context = $snapshot['policy_context'] ?? null;
        if (is_array($context) && $context !== []) {
            return $context;
        }

        $map = [];
        foreach ($submission->values as $row) {
            $key = $row->field?->name ?? (string) $row->field_id;
            $map[$key] = $row->value;
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{source: string, definitions: list<array<string, mixed>>}
     */
    private function resolveDefinitions(EApprovalForm $form, array $snapshot): array
    {
        $fromSnapshot = $snapshot['step_definitions'] ?? null;
        if (is_array($fromSnapshot) && $fromSnapshot !== []) {
            $definitions = array_values(array_filter($fromSnapshot, static fn ($row): bool => is_array($row)));
            if ($definitions !== []) {
                return [
                    'source' => 'workflow_snapshot',
                    'definitions' => $definitions,
                ];
            }
        }

        return [
            'source' => 'live_form',
            'definitions' => EApprovalWorkflowStepDefinitionSupport::definitionsFromForm($form),
        ];
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function shouldShowUnresolvedStepAsSkipped(
        int $order,
        int $currentStep,
        bool $isTerminal,
        array $step,
    ): bool {
        // Past this order, or submission already finished without activating the step.
        if ($isTerminal || ($currentStep > 0 && $order < $currentStep)) {
            return true;
        }

        // Empty dynamic approver field / list can never activate — show Skipped early.
        $type = (string) ($step['type'] ?? '');
        if (in_array($type, ['field', 'user_list', 'field_map', 'role'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $step
     */
    private function unresolvedSkipReason(array $step): string
    {
        $type = (string) ($step['type'] ?? '');

        return match ($type) {
            'field' => __('Skipped — approver field was empty.'),
            'user_list' => __('Skipped — approver list was empty.'),
            'field_map' => __('Skipped — no approver mapping for the selected value.'),
            'role' => __('Skipped — no active approver found for this role.'),
            default => __('Skipped — no approver could be assigned.'),
        };
    }

    /**
     * @param  array<string, true>  $activeStepIds
     * @return array<int, list<EApprovalRequestApproval>>
     */
    private function currentCycleApprovalsByStep(EApprovalSubmission $submission, array $activeStepIds = []): array
    {
        $cycle = (int) ($submission->approval_cycle ?: 1);
        $grouped = [];

        foreach ($submission->approvals as $approval) {
            if (! $approval instanceof EApprovalRequestApproval) {
                continue;
            }

            $approvalCycle = (int) ($approval->approval_cycle ?: 1);
            if ($approvalCycle !== $cycle) {
                continue;
            }

            // Keep invalidated rows so parallel "any / N of M" peers still diagram
            // as Not needed — do not collapse every card onto the one approved peer.
            if (in_array((string) $approval->status, ['superseded'], true)) {
                continue;
            }

            // Resume recompiles steps with new IDs. Drop only stale *pending* rows on
            // orphan compiles — keep approved/returned/invalidated so earlier steps
            // still show as Approved after "resume at returning step".
            if (
                $activeStepIds !== []
                && ! isset($activeStepIds[(string) $approval->step_id])
                && (string) $approval->status === 'pending'
            ) {
                continue;
            }

            $order = (int) ($approval->step?->step_order ?? 0);
            $grouped[$order] ??= [];
            $grouped[$order][] = $approval;
        }

        return $grouped;
    }

    /**
     * @param  list<EApprovalRequestApproval>  $approvals
     */
    private function matchApproval(array $approvals, string $userId): ?EApprovalRequestApproval
    {
        if ($approvals === []) {
            return null;
        }

        $candidates = $approvals;
        if ($userId !== '') {
            $candidates = array_values(array_filter(
                $approvals,
                static fn (EApprovalRequestApproval $approval): bool => (string) $approval->approver_id === $userId,
            ));

            // Never attach another peer's approval when this slot has a known user.
            if ($candidates === []) {
                return null;
            }
        } elseif (count($candidates) !== 1) {
            return null;
        }

        usort(
            $candidates,
            fn (EApprovalRequestApproval $left, EApprovalRequestApproval $right): int => $this->approvalMatchRank($left) <=> $this->approvalMatchRank($right),
        );

        return $candidates[0] ?? null;
    }

    private function approvalMatchRank(EApprovalRequestApproval $approval): int
    {
        return match ((string) $approval->status) {
            'pending' => 0,
            'approved' => 1,
            'returned' => 2,
            'rejected' => 3,
            'invalidated' => 4,
            'cancelled' => 5,
            default => 9,
        };
    }
}
