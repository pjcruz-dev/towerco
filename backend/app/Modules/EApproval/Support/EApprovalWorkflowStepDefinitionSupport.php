<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalWorkflowStep;
use Carbon\Carbon;

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

        if ($step->approver_type === 'user_list' && self::listSource([], $condition) === 'fixed') {
            $definition['list_source'] = 'fixed';
            $definition['fixed_approver_ids'] = self::fixedApproverIds([], $condition);
            $definition['approverId'] = null;
        }

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
            if (($condition['when_scope'] ?? null) === 'step') {
                $definition['when_scope'] = 'step';
            }
        }

        if (self::confirmSettlementEnabled($condition)) {
            $definition['confirm_settlement'] = true;
        }

        if (! empty($condition['optional'])) {
            $definition['optional'] = true;
        }

        $autoApproveDays = self::autoApproveDays([], $condition);
        if ($autoApproveDays !== null) {
            $definition['auto_approve_enabled'] = true;
            $definition['auto_approve_days'] = $autoApproveDays;
        }

        if (self::proofOfPaymentEnabled($condition)) {
            $definition['proof_of_payment'] = true;
        }

        $decisionLabels = self::storedDecisionLabels(['condition' => $condition]);
        if ($decisionLabels !== null) {
            $definition['decision_labels'] = $decisionLabels;
        }

        if (($condition['duty'] ?? null) === 'review') {
            $definition['duty'] = 'review';
        }

        $label = EApprovalFormMailConfig::cleanPublicLabel($step->label);
        if ($label !== '') {
            $definition['label'] = $label;
        }

        $mail = EApprovalFormMailConfig::mailFromCondition($condition);
        if (! EApprovalFormMailConfig::isDefaultMail($mail)) {
            $definition['mail'] = $mail;
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
            'reviewer_list', 'reviewers', 'from_reviewer_list' => 'reviewer_list',
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

        if ($type === 'user_list' && self::listSource($step, $condition) === 'fixed') {
            $definition['list_source'] = 'fixed';
            $definition['fixed_approver_ids'] = self::fixedApproverIds($step, $condition);
            $definition['approverId'] = null;
        }

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
            if (self::stepOwnedWhen($step, $condition)) {
                $definition['when_scope'] = 'step';
            }
        }

        if (self::confirmSettlementEnabled($step)) {
            $definition['confirm_settlement'] = true;
        }

        if (! empty($step['optional']) || ! empty($condition['optional'])) {
            $definition['optional'] = true;
        }

        $autoApproveDays = self::autoApproveDays($step, $condition);
        if ($autoApproveDays !== null) {
            $definition['auto_approve_enabled'] = true;
            $definition['auto_approve_days'] = $autoApproveDays;
        }

        if (self::proofOfPaymentEnabled($step)) {
            $definition['proof_of_payment'] = true;
        }

        $decisionLabels = self::storedDecisionLabels($step);
        if ($decisionLabels !== null) {
            $definition['decision_labels'] = $decisionLabels;
        }

        if (self::reviewDutyEnabled($step, $condition) || $type === 'reviewer_list') {
            $definition['duty'] = 'review';
        }

        $label = EApprovalFormMailConfig::cleanPublicLabel($step['label'] ?? '');
        if ($label !== '') {
            $definition['label'] = $label;
        }

        $mailSource = array_key_exists('mail', $step)
            ? (is_array($step['mail']) ? $step['mail'] : null)
            : ($condition['mail'] ?? null);
        $mail = EApprovalFormMailConfig::normalizeMail(is_array($mailSource) ? $mailSource : null);
        if (! EApprovalFormMailConfig::isDefaultMail($mail)) {
            $definition['mail'] = $mail;
        }

        return $definition;
    }

    /**
     * Words on Decide for this step. Defaults stay Approve, Reject, and Request revision.
     *
     * @param  array<string, mixed>  $source
     * @return array{approve: string, reject: string, revision: string}
     */
    public static function decisionLabels(array $source): array
    {
        $raw = $source['decision_labels'] ?? null;
        if (! is_array($raw)) {
            $condition = is_array($source['condition'] ?? null) ? $source['condition'] : [];
            $raw = $condition['decision_labels'] ?? [];
        }
        if (! is_array($raw)) {
            $raw = [];
        }

        return [
            'approve' => self::buttonLabel($raw['approve'] ?? null, 'Approve'),
            'reject' => self::buttonLabel($raw['reject'] ?? null, 'Reject'),
            'revision' => self::buttonLabel($raw['revision'] ?? null, 'Request revision'),
        ];
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array{approve: string, reject: string, revision: string}|null
     */
    public static function storedDecisionLabels(array $source): ?array
    {
        $labels = self::decisionLabels($source);
        if ($labels === ['approve' => 'Approve', 'reject' => 'Reject', 'revision' => 'Request revision']) {
            return null;
        }

        return $labels;
    }

    private static function buttonLabel(mixed $value, string $fallback): string
    {
        $text = trim((string) $value);

        return $text === '' ? $fallback : mb_substr($text, 0, 40);
    }

    public static function proofOfPaymentEnabled(array $source): bool
    {
        if (array_key_exists('proof_of_payment', $source)) {
            return self::flagTruthy($source['proof_of_payment']);
        }

        $condition = is_array($source['condition'] ?? null) ? $source['condition'] : [];

        return self::flagTruthy($condition['proof_of_payment'] ?? false);
    }

    private static function flagTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
    }

    /**
     * @param  array<string, mixed>  $source
     */
    public static function confirmSettlementEnabled(array $source): bool
    {
        if (array_key_exists('confirm_settlement', $source)) {
            return self::confirmSettlementTruthy($source['confirm_settlement']);
        }

        $condition = is_array($source['condition'] ?? null) ? $source['condition'] : [];

        return self::confirmSettlementTruthy($condition['confirm_settlement'] ?? false);
    }

    private static function confirmSettlementTruthy(mixed $value): bool
    {
        return $value === true || $value === 1 || $value === '1' || $value === 'true';
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
            if (self::stepOwnedWhen($step, $condition)) {
                $condition['when_scope'] = 'step';
            } else {
                unset($condition['when_scope']);
            }
        } else {
            unset($condition['when'], $condition['when_logic'], $condition['when_scope']);
        }

        if (array_key_exists('confirm_settlement', $step) || array_key_exists('confirm_settlement', $condition)) {
            if (self::confirmSettlementEnabled($step)) {
                $condition['confirm_settlement'] = true;
            } else {
                unset($condition['confirm_settlement']);
            }
        }

        if (self::reviewDutyEnabled($step, $condition) || $type === 'reviewer_list') {
            $condition['duty'] = 'review';
        } else {
            unset($condition['duty']);
        }

        if (! empty($step['optional']) || ! empty($condition['optional'])) {
            $condition['optional'] = true;
        } else {
            unset($condition['optional']);
        }

        $autoApproveDays = self::reviewDutyEnabled($step, $condition) || $type === 'reviewer_list'
            ? null
            : self::autoApproveDays($step, $condition);
        if ($autoApproveDays !== null) {
            $condition['auto_approve_enabled'] = true;
            $condition['auto_approve_days'] = $autoApproveDays;
        } else {
            unset($condition['auto_approve_enabled'], $condition['auto_approve_days']);
        }

        if (! self::reviewDutyEnabled($step, $condition) && $type !== 'reviewer_list' && self::proofOfPaymentEnabled($step)) {
            $condition['proof_of_payment'] = true;
        } else {
            unset($condition['proof_of_payment']);
        }

        $decisionLabels = self::reviewDutyEnabled($step, $condition) || $type === 'reviewer_list'
            ? null
            : self::storedDecisionLabels($step);
        if ($decisionLabels !== null) {
            $condition['decision_labels'] = $decisionLabels;
        } else {
            unset($condition['decision_labels']);
        }

        if ($type === 'user_list' && self::listSource($step, $condition) === 'fixed') {
            $condition['list_source'] = 'fixed';
            $condition['fixed_approver_ids'] = self::fixedApproverIds($step, $condition);
        } else {
            unset($condition['list_source'], $condition['fixed_approver_ids']);
        }

        $mailSource = array_key_exists('mail', $step)
            ? (is_array($step['mail']) ? $step['mail'] : null)
            : ($condition['mail'] ?? null);
        $mail = EApprovalFormMailConfig::normalizeMail(is_array($mailSource) ? $mailSource : null);
        if (EApprovalFormMailConfig::isDefaultMail($mail)) {
            unset($condition['mail']);
        } else {
            $condition['mail'] = $mail;
        }

        return $condition === [] ? null : $condition;
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $condition
     */
    private static function reviewDutyEnabled(array $step, array $condition): bool
    {
        return ($step['duty'] ?? null) === 'review' || ($condition['duty'] ?? null) === 'review';
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $condition
     */
    private static function stepOwnedWhen(array $step, array $condition): bool
    {
        return ($step['when_scope'] ?? null) === 'step' || ($condition['when_scope'] ?? null) === 'step';
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $condition
     */
    /**
     * Days after which a pending approver is approved for them. Null when the step has it off.
     *
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $condition
     */
    public static function autoApproveDays(array $step, array $condition = []): ?int
    {
        $enabled = filter_var(
            $step['auto_approve_enabled'] ?? $condition['auto_approve_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        if ($enabled !== true) {
            return null;
        }

        $raw = $step['auto_approve_days'] ?? $condition['auto_approve_days'] ?? 0;
        $days = is_numeric($raw) ? (int) $raw : 0;
        if ($days < 1) {
            return null;
        }

        return min(30, $days);
    }

    public static function approvalIsDue(?\DateTimeInterface $askedAt, int $days, \DateTimeInterface $now): bool
    {
        if ($askedAt === null || $days < 1) {
            return false;
        }

        $elapsed = (new WorkingDaysCalendar)->workingDaysBetween(
            Carbon::parse($askedAt),
            Carbon::parse($now),
        );

        return $elapsed >= $days;
    }

    public static function listSource(array $step, array $condition = []): string
    {
        $raw = strtolower(trim((string) ($step['list_source'] ?? $condition['list_source'] ?? 'field')));

        return $raw === 'fixed' ? 'fixed' : 'field';
    }

    /**
     * @param  array<string, mixed>  $step
     * @param  array<string, mixed>  $condition
     * @return list<string>
     */
    public static function fixedApproverIds(array $step, array $condition = []): array
    {
        $raw = $step['fixed_approver_ids'] ?? $condition['fixed_approver_ids'] ?? [];
        if (! is_array($raw)) {
            return [];
        }

        $ids = [];
        foreach ($raw as $id) {
            if (! is_scalar($id)) {
                continue;
            }
            $trimmed = trim((string) $id);
            if ($trimmed === '') {
                continue;
            }
            $ids[$trimmed] = $trimmed;
        }

        return array_values($ids);
    }
}
