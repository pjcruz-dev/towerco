<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;

/**
 * Stable fingerprint for workflow/field shape — ignores labels and cosmetic options.
 */
final class EApprovalFormStructureFingerprint
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function fromPayload(array $payload): string
    {
        return $this->hash(
            $this->normalizeFields(is_array($payload['fields'] ?? null) ? $payload['fields'] : []),
            $this->normalizeSteps(is_array($payload['steps'] ?? null) ? $payload['steps'] : []),
        );
    }

    public function fromForm(EApprovalForm $form): string
    {
        $form->loadMissing(['fields', 'workflowTemplate.steps']);

        $fields = $form->fields->map(static fn ($f) => [
            'name' => (string) $f->name,
            'type' => (string) $f->type,
        ])->values()->all();

        $steps = ($form->workflowTemplate?->steps ?? collect())->map(static fn ($s) => [
            'step_order' => (int) $s->step_order,
            'type' => (string) $s->approver_type,
            'approver_id' => $s->approver_id !== null ? (string) $s->approver_id : null,
            'condition' => $s->condition,
        ])->values()->all();

        return $this->hash(
            $this->normalizeFields($fields),
            $this->normalizeSteps($steps),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @return list<array{name: string, type: string}>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $name = trim((string) ($field['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized[] = [
                'name' => $name,
                'type' => strtolower(trim((string) ($field['type'] ?? 'text'))),
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return $normalized;
    }

    /**
     * @param  list<array<string, mixed>>  $steps
     * @return list<array{step_order: int, type: string, approver_id: string|null, condition: mixed}>
     */
    private function normalizeSteps(array $steps): array
    {
        $normalized = [];
        foreach ($steps as $index => $step) {
            if (! is_array($step)) {
                continue;
            }
            $type = strtolower(trim((string) ($step['type'] ?? $step['approver_type'] ?? 'user')));
            $approverId = $step['approverId'] ?? $step['approver_id'] ?? null;
            $normalized[] = [
                'step_order' => (int) ($step['step_order'] ?? $index + 1),
                'type' => $type,
                'approver_id' => $approverId !== null && $approverId !== '' ? (string) $approverId : null,
                'condition' => $step['condition'] ?? null,
            ];
        }

        usort($normalized, static fn (array $a, array $b): int => $a['step_order'] <=> $b['step_order']);

        return $normalized;
    }

    /**
     * @param  list<array{name: string, type: string}>  $fields
     * @param  list<array{step_order: int, type: string, approver_id: string|null, condition: mixed}>  $steps
     */
    private function hash(array $fields, array $steps): string
    {
        return hash('sha256', json_encode(['fields' => $fields, 'steps' => $steps], JSON_THROW_ON_ERROR));
    }
}
