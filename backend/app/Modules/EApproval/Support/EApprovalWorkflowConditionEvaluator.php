<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalWorkflowConditionEvaluator
{
    /**
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $values
     */
    public function matchesAll(array $conditions, array $values): bool
    {
        return $this->matchesWhen($conditions, $values, EApprovalWhenLogic::AND);
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $values
     */
    public function matchesAny(array $conditions, array $values): bool
    {
        return $this->matchesWhen($conditions, $values, EApprovalWhenLogic::OR);
    }

    /**
     * @param  list<array<string, mixed>>  $conditions
     * @param  array<string, mixed>  $values
     */
    public function matchesWhen(array $conditions, array $values, string $logic = EApprovalWhenLogic::AND): bool
    {
        if ($conditions === []) {
            return true;
        }

        $logic = EApprovalWhenLogic::normalize($logic);
        $matchedAny = false;

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                if ($logic === EApprovalWhenLogic::AND) {
                    return false;
                }

                continue;
            }

            $matched = $this->matches($condition, $values);
            if ($logic === EApprovalWhenLogic::AND && ! $matched) {
                return false;
            }

            if ($logic === EApprovalWhenLogic::OR && $matched) {
                return true;
            }

            $matchedAny = $matchedAny || $matched;
        }

        return $logic === EApprovalWhenLogic::AND ? true : $matchedAny;
    }

    /**
     * Evaluate a stored step condition payload (`when` list + optional `when_logic`),
     * falling back to legacy single-field condition shapes.
     *
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $values
     */
    public function matchesStoredCondition(?array $condition, array $values): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        $when = $condition['when'] ?? null;
        if (is_array($when)) {
            $list = [];
            foreach ($when as $entry) {
                if (is_array($entry)) {
                    $list[] = $entry;
                }
            }

            return $this->matchesWhen(
                $list,
                $values,
                EApprovalWhenLogic::fromDefinition(null, $condition),
            );
        }

        if (empty($condition['field'])) {
            return true;
        }

        return $this->matches($condition, $values);
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, mixed>  $values
     */
    public function matches(array $condition, array $values): bool
    {
        $field = trim((string) ($condition['field'] ?? ''));
        if ($field === '') {
            return false;
        }

        $operator = strtolower(trim((string) ($condition['operator'] ?? 'equals')));
        $expected = $condition['value'] ?? null;
        $raw = $values[$field] ?? null;
        $actual = is_scalar($raw) ? trim((string) $raw) : '';

        return match ($operator) {
            'equals', 'eq', '==' => $this->compareEquals($actual, $expected),
            'not_equals', 'neq', '!=' => ! $this->compareEquals($actual, $expected),
            'contains' => $actual !== '' && str_contains(
                strtolower($actual),
                strtolower(trim((string) $expected)),
            ),
            // Empty values never match a band. A non-numeric boundary (a choice
            // such as ATC) is an exact match: ≤ means "is", > means "is not".
            'gt', '>' => $this->compareBound($actual, $expected, 'gt'),
            'gte', '>=' => $this->compareBound($actual, $expected, 'gte'),
            'lt', '<' => $this->compareBound($actual, $expected, 'lt'),
            'lte', '<=' => $this->compareBound($actual, $expected, 'lte'),
            'is_empty' => $actual === '',
            'is_not_empty' => $actual !== '',
            'in' => $this->valueInList($actual, $expected),
            default => $this->compareEquals($actual, $expected),
        };
    }

    private function compareEquals(string $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            return (float) $actual === (float) $expected;
        }

        return strtolower($actual) === strtolower(trim((string) $expected));
    }

    /**
     * Amount boundaries stay numeric. Any other boundary matches the value itself.
     */
    private function compareBound(string $actual, mixed $expected, string $operator): bool
    {
        if ($actual === '') {
            return false;
        }

        if (! is_numeric($actual) || ! is_numeric($expected)) {
            $same = $this->compareEquals($actual, $expected);

            return $operator === 'gt' || $operator === 'gte' ? ! $same : $same;
        }

        $compared = $this->numericCompare($actual, $expected);

        return match ($operator) {
            'gt' => $compared === 1,
            'gte' => $compared >= 0,
            'lt' => $compared === -1,
            default => $compared <= 0,
        };
    }

    private function numericCompare(string $actual, mixed $expected): int
    {
        if (! is_numeric($actual) || ! is_numeric($expected)) {
            return -1;
        }

        return (float) $actual <=> (float) $expected;
    }

    private function valueInList(string $actual, mixed $expected): bool
    {
        $items = is_array($expected) ? $expected : array_map('trim', explode(',', (string) $expected));
        $needle = strtolower($actual);

        foreach ($items as $item) {
            if (strtolower(trim((string) $item)) === $needle) {
                return true;
            }
        }

        return false;
    }
}
