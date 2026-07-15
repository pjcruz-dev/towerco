<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Support\EApprovalFormPolicySupport;

final class ProcurementFormSchemaPresenter
{
    /**
     * @return array{form: array<string, mixed>|null, fields: list<array<string, mixed>>}
     */
    public function present(?EApprovalForm $form): array
    {
        if ($form === null) {
            return ['form' => null, 'fields' => []];
        }

        $form->loadMissing('fields');

        return [
            'form' => [
                'id' => (string) $form->id,
                'name' => $form->name,
                'description' => $form->description,
                'status' => $form->status,
                'metadata' => array_merge(
                    is_array($form->metadata_json) ? $form->metadata_json : [],
                    [
                        'effective_workflow_source' => EApprovalFormPolicySupport::effectiveWorkflowSource($form),
                    ],
                ),
            ],
            'fields' => $form->fields->map(static fn ($field) => [
                'id' => (string) $field->id,
                'type' => $field->type,
                'name' => $field->name,
                'label' => $field->label,
                'semantic_type' => $field->semantic_type,
                'step_order' => $field->step_order,
                'validation' => $field->validation,
                'options' => $field->options,
            ])->values()->all(),
        ];
    }
}
