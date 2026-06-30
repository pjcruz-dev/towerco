<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Support\EApprovalWorkflowConditionEvaluator;

final class EApprovalFormWorkflowRulesMatcher
{
    public function __construct(
        private readonly EApprovalWorkflowConditionEvaluator $evaluator,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  array<string, mixed>  $values
     * @return array{rule: array<string, mixed>}|null
     */
    public function match(array $rules, array $values): ?array
    {
        foreach ($rules as $rule) {
            if (! is_array($rule)) {
                continue;
            }

            $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
            if ($this->conditionsMatch($conditions, $values)) {
                return ['rule' => $rule];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $values
     */
    private function conditionsMatch(array $conditions, array $values): bool
    {
        if ($conditions === []) {
            return false;
        }

        return $this->evaluator->matchesAll($conditions, $values);
    }
}
