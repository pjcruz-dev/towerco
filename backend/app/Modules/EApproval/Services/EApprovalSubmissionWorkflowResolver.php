<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use Illuminate\Support\Collection;

/**
 * Resolves which workflow steps apply when advancing an in-flight submission.
 */
final class EApprovalSubmissionWorkflowResolver
{
    /**
     * @return Collection<int, EApprovalWorkflowStep>
     */
    public function stepsForAdvance(EApprovalSubmission $submission): Collection
    {
        $submission->loadMissing(['form.workflowTemplate.steps']);

        $live = $submission->form?->workflowTemplate?->steps ?? collect();
        if ($live->isEmpty()) {
            return $live;
        }

        if (! in_array((string) $submission->status, EApprovalSubmissionStatus::open(), true)) {
            return $live->sortBy('step_order')->values();
        }

        $snapshotSteps = $this->parseSnapshotSteps($submission);
        if ($snapshotSteps === []) {
            return $live->sortBy('step_order')->values();
        }

        $byId = $live->keyBy(static fn (EApprovalWorkflowStep $s) => (string) $s->id);
        $resolved = collect();

        foreach ($snapshotSteps as $snapshot) {
            $id = (string) ($snapshot['id'] ?? '');
            if ($id !== '' && $byId->has($id)) {
                $resolved->push($byId->get($id));

                continue;
            }

            $order = (int) ($snapshot['step_order'] ?? 0);
            $fallback = $live->first(
                static fn (EApprovalWorkflowStep $s) => (int) $s->step_order === $order
                    && (string) $s->approver_type === (string) ($snapshot['approver_type'] ?? $snapshot['type'] ?? ''),
            );
            if ($fallback instanceof EApprovalWorkflowStep) {
                $resolved->push($fallback);
            }
        }

        if ($resolved->isEmpty()) {
            return $live->sortBy('step_order')->values();
        }

        return $resolved->sortBy('step_order')->values();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function parseSnapshotSteps(EApprovalSubmission $submission): array
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

        $steps = is_array($decoded) ? ($decoded['steps'] ?? null) : null;
        if (! is_array($steps)) {
            return [];
        }

        return array_values(array_filter($steps, static fn ($s): bool => is_array($s)));
    }
}
