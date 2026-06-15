<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Models\EApprovalFormValue;
use App\Modules\EApproval\Models\EApprovalRequestApproval;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use App\Modules\EApproval\Models\EApprovalWorkflowTemplate;
use Illuminate\Support\Str;

/**
 * Updates form fields and workflow steps in place so historical submissions keep FK integrity.
 */
final class EApprovalFormSyncService
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function sync(EApprovalForm $form, array $payload): void
    {
        $this->syncFields($form, is_array($payload['fields'] ?? null) ? $payload['fields'] : []);
        $this->syncWorkflow(
            $form,
            is_array($payload['steps'] ?? null) ? $payload['steps'] : [],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $fieldsPayload
     */
    private function syncFields(EApprovalForm $form, array $fieldsPayload): void
    {
        $existingById = EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->get()
            ->keyBy(static fn (EApprovalFormField $f) => (string) $f->id);

        $existingByName = $existingById->values()->keyBy(static fn (EApprovalFormField $f) => (string) $f->name);

        $referencedFieldIds = EApprovalFormValue::query()
            ->whereIn('submission_id', EApprovalSubmission::query()->where('form_id', $form->id)->select('id'))
            ->pluck('field_id')
            ->map(static fn ($id) => (string) $id)
            ->unique()
            ->all();

        $keptIds = [];
        $order = 0;

        foreach ($fieldsPayload as $field) {
            if (! is_array($field)) {
                continue;
            }

            $payloadId = isset($field['id']) ? trim((string) $field['id']) : '';
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $match = ($payloadId !== '' && $existingById->has($payloadId))
                ? $existingById->get($payloadId)
                : $existingByName->get($name);

            $attributes = [
                'type' => (string) $field['type'],
                'name' => $name,
                'label' => (string) $field['label'],
                'semantic_type' => $field['semantic_type'] ?? null,
                'behavior' => is_array($field['behavior'] ?? null) ? $field['behavior'] : null,
                'formula' => $field['formula'] ?? null,
                'validation' => is_array($field['validation'] ?? null) ? $field['validation'] : null,
                'options' => is_array($field['options'] ?? null) ? $field['options'] : null,
                'step_order' => (int) ($field['step_order'] ?? $order++),
            ];

            if ($match instanceof EApprovalFormField) {
                $match->fill($attributes);
                $match->save();
                $keptIds[] = (string) $match->id;
                continue;
            }

            $created = EApprovalFormField::query()->create([
                'id' => (string) Str::uuid(),
                'form_id' => $form->id,
                ...$attributes,
            ]);
            $keptIds[] = (string) $created->id;
        }

        $deletable = EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->when($keptIds !== [], static fn ($q) => $q->whereNotIn('id', $keptIds))
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        foreach ($deletable as $fieldId) {
            if (in_array($fieldId, $referencedFieldIds, true)) {
                continue;
            }
            EApprovalFormField::query()->where('id', $fieldId)->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $stepsPayload
     */
    private function syncWorkflow(EApprovalForm $form, array $stepsPayload): void
    {
        $template = EApprovalWorkflowTemplate::query()->firstOrCreate(
            ['form_id' => $form->id],
            ['id' => (string) Str::uuid()],
        );

        $existingById = EApprovalWorkflowStep::query()
            ->where('template_id', $template->id)
            ->get()
            ->keyBy(static fn (EApprovalWorkflowStep $s) => (string) $s->id);

        $referencedStepIds = EApprovalRequestApproval::query()
            ->whereIn('submission_id', EApprovalSubmission::query()->where('form_id', $form->id)->select('id'))
            ->pluck('step_id')
            ->map(static fn ($id) => (string) $id)
            ->unique()
            ->all();

        $keptIds = [];

        foreach ($stepsPayload as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $payloadId = isset($step['id']) ? trim((string) $step['id']) : '';
            $type = (string) ($step['type'] ?? $step['approver_type'] ?? 'user');
            $approverId = isset($step['approverId']) ? (string) $step['approverId'] : ($step['approver_id'] ?? null);

            $attributes = [
                'step_order' => (int) ($step['step_order'] ?? $index + 1),
                'approver_type' => $type,
                'approver_id' => $approverId,
                'condition' => is_array($step['condition'] ?? null) ? $step['condition'] : null,
            ];

            $match = ($payloadId !== '' && $existingById->has($payloadId))
                ? $existingById->get($payloadId)
                : $existingById->first(
                    static fn (EApprovalWorkflowStep $s) => (int) $s->step_order === (int) $attributes['step_order']
                        && (string) $s->approver_type === (string) $attributes['approver_type']
                        && (string) ($s->approver_id ?? '') === (string) ($attributes['approver_id'] ?? ''),
                );

            if ($match instanceof EApprovalWorkflowStep) {
                $match->fill($attributes);
                $match->save();
                $keptIds[] = (string) $match->id;
                continue;
            }

            $created = EApprovalWorkflowStep::query()->create([
                'id' => (string) Str::uuid(),
                'template_id' => $template->id,
                ...$attributes,
            ]);
            $keptIds[] = (string) $created->id;
        }

        $deletable = EApprovalWorkflowStep::query()
            ->where('template_id', $template->id)
            ->when($keptIds !== [], static fn ($q) => $q->whereNotIn('id', $keptIds))
            ->pluck('id')
            ->map(static fn ($id) => (string) $id)
            ->all();

        foreach ($deletable as $stepId) {
            if (in_array($stepId, $referencedStepIds, true)) {
                continue;
            }
            EApprovalWorkflowStep::query()->where('id', $stepId)->delete();
        }
    }
}
