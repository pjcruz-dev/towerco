<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalFormField;

final class EApprovalFieldVisibilityEvaluator
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function isVisible(EApprovalFormField $field, array $values): bool
    {
        $rule = $this->parseRule($field);
        if ($rule === null) {
            return true;
        }

        $controlName = $rule['field'];
        $raw = $values[$controlName] ?? $values[(string) $field->id] ?? '';
        $controlValue = $this->normalizeValue($raw);
        $matches = $this->conditionMatches($rule['operator'], $controlValue, $rule['value'] ?? '');

        return $rule['mode'] === 'show_when' ? $matches : ! $matches;
    }

    /**
     * @return array{mode: string, field: string, operator: string, value?: string}|null
     */
    private function parseRule(EApprovalFormField $field): ?array
    {
        $options = is_array($field->options) ? $field->options : [];
        $visibility = $options['visibility'] ?? null;
        if (! is_array($visibility)) {
            return null;
        }

        $mode = ($visibility['mode'] ?? '') === 'hide_when' ? 'hide_when' : (($visibility['mode'] ?? '') === 'show_when' ? 'show_when' : null);
        $whenField = trim((string) ($visibility['field'] ?? ''));
        $operator = (string) ($visibility['operator'] ?? '');
        $allowed = ['equals', 'not_equals', 'filled', 'empty', 'contains'];

        if ($mode === null || $whenField === '' || ! in_array($operator, $allowed, true)) {
            return null;
        }

        return [
            'mode' => $mode,
            'field' => $whenField,
            'operator' => $operator,
            'value' => isset($visibility['value']) ? (string) $visibility['value'] : '',
        ];
    }

    private function conditionMatches(string $operator, string $value, string $expected): bool
    {
        return match ($operator) {
            'empty' => $value === '',
            'filled' => $value !== '' && $value !== 'false',
            'equals' => strcasecmp($value, $expected) === 0,
            'not_equals' => strcasecmp($value, $expected) !== 0,
            'contains' => $expected !== '' && stripos($value, $expected) !== false,
            default => false,
        };
    }

    private function normalizeValue(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        if (is_bool($raw)) {
            return $raw ? 'true' : 'false';
        }

        if (is_scalar($raw)) {
            return trim((string) $raw);
        }

        return '';
    }
}
