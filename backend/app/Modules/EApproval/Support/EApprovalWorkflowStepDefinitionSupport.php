<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;

final class EApprovalWorkflowStepDefinitionSupport
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function definitionsFromForm(EApprovalForm $form): array
    {
        if (EApprovalFormWorkflowRulesSupport::usesRulesMode($form)) {
            return self::definitionsFromLegacyRules($form);
        }

        $form->loadMissing('workflowTemplate.steps');

        return ($form->workflowTemplate?->steps ?? collect())
            ->sortBy('step_order')
            ->values()
            ->map(static fn (EApprovalWorkflowStep $step): array => self::fromModel($step))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function definitionsFromLegacyRules(EApprovalForm $form): array
    {
        $metadata = is_array($form->metadata_json) ? $form->metadata_json : [];
        $definitions = [];

        foreach (EApprovalFormWorkflowRulesSupport::rulesFromForm($form) as $rule) {
            $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
            $steps = is_array($rule['steps'] ?? null) ? $rule['steps'] : [];

            foreach (array_values($steps) as $index => $step) {
                if (! is_array($step)) {
                    continue;
                }

                $definition = self::normalizePayloadStep($step, $index + 1);
                if ($conditions !== []) {
                    $definition['when'] = $conditions;
                }

                $definitions[] = $definition;
            }
        }

        foreach (EApprovalFormWorkflowRulesSupport::defaultStepsFromForm($form) as $index => $step) {
            if (! is_array($step)) {
                continue;
            }

            $definitions[] = self::normalizePayloadStep($step, count($definitions) + $index + 1);
        }

        return $definitions;
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromModel(EApprovalWorkflowStep $step): array
    {
        $condition = is_array($step->condition) ? $step->condition : [];
        $definition = [
            'type' => (string) $step->approver_type,
            'approverId' => $step->approver_id,
            'step_order' => (int) $step->step_order,
        ];

        if ($step->approver_type === 'field_map') {
            $definition['source_field'] = $step->approver_id;
            $definition['mappings'] = is_array($condition['mappings'] ?? null) ? $condition['mappings'] : [];
            $definition['default_approver_id'] = $condition['default_approver_id'] ?? null;
        }

        $when = self::whenFromDefinition($definition, $condition);
        if ($when !== []) {
            $definition['when'] = $when;
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>
     */
    public static function normalizePayloadStep(array $step, int $fallbackOrder): array
    {
        $type = match (strtolower(trim((string) ($step['type'] ?? $step['approver_type'] ?? 'user')))) {
            'fixed', 'fixed_user', 'fixeduser' => 'user',
            'approver_field', 'from_field', 'from_approver_field' => 'field',
            'direct_manager', 'entra_manager' => 'manager',
            'field_map', 'map_field', 'mapped_field' => 'field_map',
            default => strtolower(trim((string) ($step['type'] ?? $step['approver_type'] ?? 'user'))),
        };

        $approverId = isset($step['approverId'])
            ? trim((string) $step['approverId'])
            : trim((string) ($step['approver_id'] ?? ''));
        $condition = is_array($step['condition'] ?? null) ? $step['condition'] : [];

        $definition = [
            'type' => $type,
            'approverId' => $approverId !== '' ? $approverId : null,
            'step_order' => (int) ($step['step_order'] ?? $fallbackOrder),
        ];

        if ($type === 'field_map') {
            $sourceField = trim((string) ($step['source_field'] ?? $approverId));
            $definition['source_field'] = $sourceField !== '' ? $sourceField : null;
            $definition['mappings'] = is_array($step['mappings'] ?? null)
                ? $step['mappings']
                : (is_array($condition['mappings'] ?? null) ? $condition['mappings'] : []);
            $definition['default_approver_id'] = $step['default_approver_id'] ?? $condition['default_approver_id'] ?? null;
        }

        $when = self::whenFromDefinition($step, $condition);
        if ($when !== []) {
            $definition['when'] = $when;
        }

        return $definition;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $condition
     * @return list<array<string, mixed>>
     */
    public static function whenFromDefinition(array $step, array $condition): array
    {
        $when = $step['when'] ?? $condition['when'] ?? [];

        if (! is_array($when)) {
            return [];
        }

        $normalized = [];
        foreach ($when as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $field = trim((string) ($entry['field'] ?? ''));
            if ($field === '') {
                continue;
            }

            $normalized[] = $entry;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $step
     * @return array<string, mixed>|null
     */
    public static function buildStoredCondition(array $step, string $type): ?array
    {
        $condition = is_array($step['condition'] ?? null) ? $step['condition'] : [];
        $when = self::whenFromDefinition($step, $condition);

        if ($type === 'field_map') {
            $condition = [
                'mappings' => is_array($step['mappings'] ?? null)
                    ? $step['mappings']
                    : (is_array($condition['mappings'] ?? null) ? $condition['mappings'] : []),
                'default_approver_id' => $step['default_approver_id'] ?? $condition['default_approver_id'] ?? null,
            ];
        } else {
            $condition = array_filter(
                $condition,
                static fn (mixed $value, string $key): bool => ! in_array($key, ['mappings', 'default_approver_id'], true),
                ARRAY_FILTER_USE_BOTH,
            );
        }

        if ($when !== []) {
            $condition['when'] = $when;
        } else {
            unset($condition['when']);
        }

        return $condition === [] ? null : $condition;
    }
}
