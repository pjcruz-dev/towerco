<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use Illuminate\Support\Collection;

/**
 * Resolves which workflow steps apply when advancing an in-flight submission.
 *
 * Conditional / policy-compiled submissions persist a dedicated step set with
 * compiled_for_submission_id. Advancement must use those rows — never the live
 * form template — because template step_order values are not the same as the
 * compacted runtime path.
 */
final class EApprovalSubmissionWorkflowResolver
{
    /**
     * @return Collection<int, EApprovalWorkflowStep>
     */
    public function stepsForAdvance(EApprovalSubmission $submission): Collection
    {
        $compiled = EApprovalWorkflowStep::query()
            ->where('compiled_for_submission_id', (string) $submission->id)
            ->orderBy('step_order')
            ->orderBy('id')
            ->get();

        if ($compiled->isNotEmpty()) {
            return $compiled->values();
        }

        $fromSnapshotIds = $this->stepsFromSnapshotIds($submission);
        if ($fromSnapshotIds->isNotEmpty()) {
            return $fromSnapshotIds;
        }

        $submission->loadMissing(['form.workflowTemplate.steps']);

        return ($submission->form?->workflowTemplate?->steps ?? collect())
            ->sortBy('step_order')
            ->values();
    }

    /**
     * @return Collection<int, EApprovalWorkflowStep>
     */
    private function stepsFromSnapshotIds(EApprovalSubmission $submission): Collection
    {
        $snapshotSteps = $this->parseSnapshotSteps($submission);
        if ($snapshotSteps === []) {
            return collect();
        }

        $ids = [];
        foreach ($snapshotSteps as $snapshot) {
            $id = trim((string) ($snapshot['id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        if ($ids === []) {
            return collect();
        }

        $byId = EApprovalWorkflowStep::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get()
            ->keyBy(static fn (EApprovalWorkflowStep $step): string => (string) $step->id);

        $resolved = collect();
        foreach ($snapshotSteps as $snapshot) {
            $id = trim((string) ($snapshot['id'] ?? ''));
            if ($id !== '' && $byId->has($id)) {
                $resolved->push($byId->get($id));
            }
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
