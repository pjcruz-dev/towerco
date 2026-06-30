<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use Illuminate\Support\Collection;

final class EApprovalSubmissionWorkflowPreparer
{
    public function __construct(
        private readonly EApprovalSubmissionSnapshotService $snapshots,
        private readonly EApprovalApprovalPolicyCompilerService $policyCompiler,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array{
     *   schema_snapshot_json: string,
     *   workflow_snapshot_json: string,
     *   workflow_version_id: string,
     *   approval_policy_version_id: string|null,
     *   approval_policy_label: string|null,
     *   steps: Collection<int, EApprovalWorkflowStep>
     * }
     */
    public function prepare(EApprovalForm $form, array $values, string $submissionId): array
    {
        $base = $this->snapshots->capture($form);
        $compiled = $this->policyCompiler->compileForSubmit($form, $values, $submissionId);

        $workflowSnapshot = $compiled['snapshot'];
        $workflowVersionId = hash('sha256', json_encode($workflowSnapshot['steps'] ?? [], JSON_THROW_ON_ERROR));

        return [
            'schema_snapshot_json' => $base['schema_snapshot_json'],
            'workflow_snapshot_json' => json_encode($workflowSnapshot, JSON_THROW_ON_ERROR),
            'workflow_version_id' => $workflowVersionId,
            'approval_policy_version_id' => $compiled['approval_policy_version_id'],
            'approval_policy_label' => $compiled['approval_policy_label'],
            'steps' => $compiled['steps'],
        ];
    }
}
