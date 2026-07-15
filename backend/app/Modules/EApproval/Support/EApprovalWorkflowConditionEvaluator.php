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
        if ($conditions === []) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                return false;
            }

            if (! $this->matches($condition, $values)) {
                return false;
            }
        }

        return true;
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
            'equals', 'eq' => $this->compareEquals($actual, $expected),
            'not_equals', 'neq' => ! $this->compareEquals($actual, $expected),
            'contains' => $actual !== '' && str_contains(
                strtolower($actual),
                strtolower(trim((string) $expected)),
            ),
            'gt' => $this->numericCompare($actual, $expected) === 1,
            'gte' => $this->numericCompare($actual, $expected) >= 0,
            'lt' => $this->numericCompare($actual, $expected) === -1,
            'lte' => $this->numericCompare($actual, $expected) <= 0,
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
