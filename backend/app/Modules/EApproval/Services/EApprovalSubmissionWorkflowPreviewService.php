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

        $approvalsByStep = $this->currentCycleApprovalsByStep($submission);

        $resolved = [];
        foreach ($preview['resolved_steps'] ?? [] as $step) {
            if (! is_array($step)) {
                continue;
            }

            $order = (int) ($step['step_order'] ?? 0);
            $userId = isset($step['resolved_user_id']) ? (string) $step['resolved_user_id'] : '';
            $match = $this->matchApproval($approvalsByStep[$order] ?? [], $userId);

            $resolved[] = [
                ...$step,
                'path_reason' => __('Conditions matched — step runs for this submission.'),
                'runtime_status' => $match?->status,
                'approval_id' => $match?->id !== null ? (string) $match->id : null,
                'acted_at' => $match?->acted_at?->toIso8601String(),
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
     * @return array<int, list<EApprovalRequestApproval>>
     */
    private function currentCycleApprovalsByStep(EApprovalSubmission $submission): array
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

            if (in_array((string) $approval->status, ['superseded', 'invalidated'], true)) {
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

        if ($userId !== '') {
            foreach ($approvals as $approval) {
                if ((string) $approval->approver_id === $userId) {
                    return $approval;
                }
            }
        }

        return count($approvals) === 1 ? $approvals[0] : null;
    }
}
