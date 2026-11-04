<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;

/**
 * The live template row for one compiled step.
 * Same person, same when-clause, and the same time they appear.
 * A later copy of that person is a different step. Compacted step numbers are not an identity.
 */
final class EApprovalLiveStepMatcher
{
    public static function match(EApprovalSubmission $submission, ?EApprovalWorkflowStep $compiled): ?EApprovalWorkflowStep
    {
        if ($compiled === null) {
            return null;
        }

        $submission->loadMissing('form.workflowTemplate.steps');
        $templateSteps = $submission->form?->workflowTemplate?->steps;
        if ($templateSteps === null || $templateSteps->isEmpty()) {
            return null;
        }

        $whenKey = self::whenKey($compiled);
        $matches = $templateSteps
            ->sortBy(static fn (EApprovalWorkflowStep $step): string => sprintf('%08d-%s', (int) $step->step_order, (string) $step->id))
            ->filter(static fn (EApprovalWorkflowStep $step): bool => self::sameApproverAndWhen($compiled, $step, $whenKey))
            ->values();
        if ($matches->isEmpty()) {
            return null;
        }

        $picked = $matches->get(self::compiledOccurrenceIndex($compiled, $whenKey));

        return $picked instanceof EApprovalWorkflowStep ? $picked : null;
    }

    public static function whenKey(EApprovalWorkflowStep $step): string
    {
        $condition = is_array($step->condition) ? $step->condition : [];
        $when = $condition['when'] ?? [];
        if (! is_array($when)) {
            return '';
        }

        $parts = [];
        foreach ($when as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $parts[] = strtolower(trim((string) ($entry['field'] ?? '')))
                .'|'.strtolower(trim((string) ($entry['operator'] ?? 'equals')))
                .'|'.trim((string) ($entry['value'] ?? ''));
        }
        sort($parts);

        return implode(';', $parts);
    }

    private static function compiledOccurrenceIndex(EApprovalWorkflowStep $compiled, string $whenKey): int
    {
        $submissionId = trim((string) ($compiled->compiled_for_submission_id ?? ''));
        if ($submissionId === '') {
            return 0;
        }

        $siblings = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', $submissionId)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        $index = 0;
        foreach ($siblings as $step) {
            if ((string) $step->id === (string) $compiled->id) {
                return $index;
            }
            if (self::sameApproverAndWhen($compiled, $step, $whenKey)) {
                $index++;
            }
        }

        return $index;
    }

    private static function sameApproverAndWhen(EApprovalWorkflowStep $compiled, EApprovalWorkflowStep $candidate, string $whenKey): bool
    {
        if (self::whenKey($candidate) !== $whenKey) {
            return false;
        }

        $approverId = trim((string) ($compiled->approver_id ?? ''));
        if ($approverId !== '' && trim((string) ($candidate->approver_id ?? '')) === $approverId) {
            return true;
        }

        $condition = is_array($candidate->condition) ? $candidate->condition : [];
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

        return $approverId === ''
            && trim((string) ($candidate->approver_id ?? '')) === ''
            && (string) $candidate->approver_type === (string) $compiled->approver_type;
    }
}
