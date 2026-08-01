<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;

final class EApprovalWorkflowStepDefinitionSupport
{
    /**
     * Remap distinct step_order values to 1..N while keeping parallel siblings
     * that share the same original order on the same compacted order.
     *
     * @param  list<array<string, mixed>>  $steps
     * @return list<array<string, mixed>>
     */
    public static function compactStepOrdersPreservingTies(array $steps): array
    {
        if ($steps === []) {
            return [];
        }

        $uniqueOrders = [];
        foreach (array_values($steps) as $index => $step) {
            if (! is_array($step)) {
                continue;
            }
            $order = (int) ($step['step_order'] ?? $index + 1);
            if (! in_array($order, $uniqueOrders, true)) {
                $uniqueOrders[] = $order;
            }
        }

        sort($uniqueOrders);

        $orderMap = [];
        foreach (array_values($uniqueOrders) as $index => $oldOrder) {
            $orderMap[$oldOrder] = $index + 1;
        }

        $compacted = [];
        foreach (array_values($steps) as $index => $step) {
            if (! is_array($step)) {
                continue;
            }
            $oldOrder = (int) ($step['step_order'] ?? $index + 1);
            $compacted[] = [
                ...$step,
                'step_order' => $orderMap[$oldOrder] ?? ($index + 1),
            ];
        }

        return $compacted;
    }

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

        $fallback = $condition['fallback_approver_id'] ?? null;
        if (is_string($fallback) && trim($fallback) !== '') {
            $definition['fallback_approver_id'] = trim($fallback);
        }

        $mode = EApprovalParallelMode::fromCondition($condition);
        if ($mode !== EApprovalParallelMode::ALL) {
            $definition['parallel_mode'] = $mode;
            if ($mode === EApprovalParallelMode::N_OF_M) {
                $quorum = is_numeric($condition['parallel_quorum'] ?? null)
                    ? max(1, (int) $condition['parallel_quorum'])
                    : 1;
                $definition['parallel_quorum'] = $quorum;
            }
        }

        $when = self::whenFromDefinition($definition, $condition);
        if ($when !== []) {
            $definition['when'] = $when;
            $logic = EApprovalWhenLogic::fromDefinition($definition, $condition);
            if ($logic === EApprovalWhenLogic::OR) {
                $definition['when_logic'] = EApprovalWhenLogic::OR;
            }
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
            'user_list', 'field_list', 'approver_list', 'from_approver_list' => 'user_list',
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

        $fallback = $step['fallback_approver_id'] ?? $condition['fallback_approver_id'] ?? null;
        if (is_string($fallback) && trim($fallback) !== '') {
            $definition['fallback_approver_id'] = trim($fallback);
        }

        $mode = EApprovalParallelMode::normalize(
            isset($step['parallel_mode'])
                ? (string) $step['parallel_mode']
                : (isset($condition['parallel_mode']) ? (string) $condition['parallel_mode'] : null),
        );
        if ($mode !== EApprovalParallelMode::ALL) {
            $definition['parallel_mode'] = $mode;
            if ($mode === EApprovalParallelMode::N_OF_M) {
                $raw = $step['parallel_quorum'] ?? $condition['parallel_quorum'] ?? 1;
                $definition['parallel_quorum'] = max(1, (int) $raw);
            }
        }

        $when = self::whenFromDefinition($step, $condition);
        if ($when !== []) {
            $definition['when'] = $when;
            $logic = EApprovalWhenLogic::fromDefinition($step, $condition);
            if ($logic === EApprovalWhenLogic::OR) {
                $definition['when_logic'] = EApprovalWhenLogic::OR;
            }
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

        $fallback = $step['fallback_approver_id'] ?? $condition['fallback_approver_id'] ?? null;
        if (is_string($fallback) && trim($fallback) !== '') {
            $condition['fallback_approver_id'] = trim($fallback);
        } else {
            unset($condition['fallback_approver_id']);
        }

        $mode = EApprovalParallelMode::normalize(
            isset($step['parallel_mode'])
                ? (string) $step['parallel_mode']
                : (isset($condition['parallel_mode']) ? (string) $condition['parallel_mode'] : null),
        );
        if ($mode === EApprovalParallelMode::ALL) {
            unset($condition['parallel_mode'], $condition['parallel_quorum']);
        } else {
            $condition['parallel_mode'] = $mode;
            if ($mode === EApprovalParallelMode::N_OF_M) {
                $raw = $step['parallel_quorum'] ?? $condition['parallel_quorum'] ?? 1;
                $condition['parallel_quorum'] = max(1, (int) $raw);
            } else {
                unset($condition['parallel_quorum']);
            }
        }

        if ($when !== []) {
            $condition['when'] = $when;
            $logic = EApprovalWhenLogic::fromDefinition($step, $condition);
            if ($logic === EApprovalWhenLogic::OR) {
                $condition['when_logic'] = EApprovalWhenLogic::OR;
            } else {
                unset($condition['when_logic']);
            }
        } else {
            unset($condition['when'], $condition['when_logic']);
        }

        return $condition === [] ? null : $condition;
    }
}
