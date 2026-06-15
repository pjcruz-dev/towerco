<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
final class EApprovalSubmissionSnapshotService
{
    /**
     * @return array{schema_snapshot_json: string, workflow_snapshot_json: string, workflow_version_id: string}
     */
    public function capture(EApprovalForm $form): array
    {
        $form->loadMissing(['fields', 'workflowTemplate.steps']);

        $schemaPayload = [
            'form' => [
                'id' => $form->id,
                'name' => $form->name,
                'category' => $form->category,
                'schema_version' => (int) $form->schema_version,
                'metadata_json' => $form->metadata_json,
            ],
            'fields' => $form->fields->map(static fn ($f) => [
                'id' => $f->id,
                'type' => $f->type,
                'name' => $f->name,
                'label' => $f->label,
                'semantic_type' => $f->semantic_type,
                'validation' => $f->validation,
                'options' => $f->options,
            ])->values()->all(),
        ];

        $steps = $form->workflowTemplate?->steps ?? collect();
        $workflowPayload = [
            'template_id' => $form->workflowTemplate?->id,
            'steps' => $steps->map(static fn ($s) => [
                'id' => $s->id,
                'step_order' => $s->step_order,
                'approver_type' => $s->approver_type,
                'approver_id' => $s->approver_id,
                'condition' => $s->condition,
            ])->values()->all(),
        ];

        $versionSource = json_encode($workflowPayload['steps']);
        $workflowVersionId = hash('sha256', (string) $versionSource);

        return [
            'schema_snapshot_json' => json_encode($schemaPayload, JSON_THROW_ON_ERROR),
            'workflow_snapshot_json' => json_encode($workflowPayload, JSON_THROW_ON_ERROR),
            'workflow_version_id' => $workflowVersionId,
        ];
    }
}
