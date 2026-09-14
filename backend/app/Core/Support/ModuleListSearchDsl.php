<?php

declare(strict_types=1);

namespace App\Core\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Shared module-list search DSL:
 *   status:ready | status=ready  → equality
 *   status:pending|approved      → OR equality (pipe-separated values)
 *   status!=closed               → not equal
 *   title~outage                 → contains (LIKE)
 *   created>=2026-01-01          → comparison (date/number columns)
 *   status:open OR priority:high → cross-key OR groups (AND within each group)
 *
 * Unrecognized tokens remain in the residual free-text string.
 */
final class ModuleListSearchDsl
{
    public const OP_EQ = 'eq';

    public const OP_NE = 'ne';

    public const OP_CONTAINS = 'contains';

    public const OP_GTE = 'gte';

    public const OP_LTE = 'lte';

    public const OP_GT = 'gt';

    public const OP_LT = 'lt';

    /** Longer operators first so `>=` wins over `>`. */
    private const TOKEN_PATTERN = '/(?:^|\s)([a-z_][a-z0-9_]*)\s*(!=|>=|<=|:|=|~|>|<)\s*("([^"]*)"|([^\s]+))/i';

    /**
     * @return array{
     *     groups: list<array{clauses: list<array{key: string, op: self::OP_*, value: string}>, residual: string}>,
     *     clauses: list<array{key: string, op: self::OP_*, value: string}>,
     *     residual: string
     * }
     */
    public static function parse(string $raw): array
    {
        $segments = self::splitTopLevelOr($raw);
        $groups = [];
        foreach ($segments as $segment) {
            $groups[] = self::parseGroup($segment);
        }

        if ($groups === []) {
            $groups[] = ['clauses' => [], 'residual' => ''];
        }

        $flatClauses = [];
        $residuals = [];
        foreach ($groups as $group) {
            foreach ($group['clauses'] as $clause) {
                $flatClauses[] = $clause;
            }
            if ($group['residual'] !== '') {
                $residuals[] = $group['residual'];
            }
        }

        return [
            'groups' => $groups,
            // First-group clauses for simple callers; prefer `groups` when OR is present.
            'clauses' => $groups[0]['clauses'],
            'residual' => implode(' ', $residuals),
        ];
    }

    /**
     * Apply allowlisted DSL clauses, then optional free-text on the residual.
     *
     * Field map entry shapes:
     * - column: string (direct column)
     * - columns: list<string> (OR across columns for contains/eq)
     * - relation + relation_column: whereHas
     * - type: exact|string (default string for contains, exact for eq/ne when type=exact)
     * - values: list<string> allowlist for eq (optional)
     * - normalize: callable(string): string|null — return null to skip clause
     * - handler: callable(Builder, string $op, string $value): void — custom clause (form fields, etc.)
     *
     * @param  array<string, array<string, mixed>>  $fields
     * @param  callable(Builder, string): void|null  $applyFreeText
     * @return array{applied: int, residual: string}
     */
    public static function apply(Builder $query, string $search, array $fields, ?callable $applyFreeText = null): array
    {
        $parsed = self::parse($search);
        $groups = $parsed['groups'];
        $applied = 0;
        $residualParts = [];

        if (count($groups) <= 1) {
            $result = self::applyGroup($query, $groups[0] ?? ['clauses' => [], 'residual' => ''], $fields, $applyFreeText);
            $applied += $result['applied'];
            if ($result['residual'] !== '') {
                $residualParts[] = $result['residual'];
            }
        } else {
            $query->where(function (Builder $outer) use ($groups, $fields, $applyFreeText, &$applied, &$residualParts): void {
                foreach ($groups as $index => $group) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $outer->{$method}(function (Builder $inner) use ($group, $fields, $applyFreeText, &$applied, &$residualParts): void {
                        $result = self::applyGroup($inner, $group, $fields, $applyFreeText);
                        $applied += $result['applied'];
                        if ($result['residual'] !== '') {
                            $residualParts[] = $result['residual'];
                        }
                    });
                }
            });
        }

        return [
            'applied' => $applied,
            'residual' => trim(implode(' ', $residualParts)),
        ];
    }

    /**
     * @param  array{clauses: list<array{key: string, op: string, value: string}>, residual: string}  $group
     * @param  array<string, array<string, mixed>>  $fields
     * @param  callable(Builder, string): void|null  $applyFreeText
     * @return array{applied: int, residual: string}
     */
    private static function applyGroup(Builder $query, array $group, array $fields, ?callable $applyFreeText): array
    {
        $applied = 0;
        $residual = $group['residual'];

        foreach ($group['clauses'] as $clause) {
            $field = $fields[$clause['key']] ?? null;
            if ($field === null) {
                $token = $clause['key'].self::opToken($clause['op']).self::quoteIfNeeded($clause['value']);
                $residual = trim($residual.' '.$token);
                continue;
            }

            $value = $clause['value'];
            if (isset($field['normalize']) && is_callable($field['normalize'])) {
                $normalized = $field['normalize']($value, $clause['op']);
                if ($normalized === null) {
                    continue;
                }
                $value = (string) $normalized;
            }

            $allowed = $field['values'] ?? null;
            if (is_array($allowed) && in_array($clause['op'], [self::OP_EQ, self::OP_NE], true)) {
                $parts = self::splitOrValues($value);
                $normalizedParts = [];
                foreach ($parts as $part) {
                    $matched = null;
                    foreach ($allowed as $item) {
                        if (strcasecmp((string) $item, $part) === 0) {
                            $matched = (string) $item;
                            break;
                        }
                    }
                    if ($matched === null) {
                        $normalizedParts = [];
                        break;
                    }
                    $normalizedParts[] = $matched;
                }
                if ($normalizedParts === []) {
                    continue;
                }
                $value = implode('|', $normalizedParts);
            }

            self::applyClause($query, $field, $clause['op'], $value);
            $applied++;
        }

        if ($residual !== '' && $applyFreeText !== null) {
            $applyFreeText($query, $residual);
        }

        return [
            'applied' => $applied,
            'residual' => $residual,
        ];
    }

    /**
     * @return array{clauses: list<array{key: string, op: string, value: string}>, residual: string}
     */
    private static function parseGroup(string $raw): array
    {
        $clauses = [];
        $residual = $raw;
        $seenKeys = [];

        if (! preg_match_all(self::TOKEN_PATTERN, $raw, $matches, PREG_SET_ORDER)) {
            return [
                'clauses' => [],
                'residual' => trim(preg_replace('/\s+/', ' ', $raw) ?? $raw),
            ];
        }

        foreach ($matches as $match) {
            $key = strtolower((string) ($match[1] ?? ''));
            $opRaw = (string) ($match[2] ?? ':');
            $value = trim((string) (($match[4] ?? '') !== '' ? $match[4] : ($match[5] ?? '')));
            if ($key === '' || $value === '') {
                continue;
            }

            $op = match ($opRaw) {
                '!=' => self::OP_NE,
                '~' => self::OP_CONTAINS,
                '>=' => self::OP_GTE,
                '<=' => self::OP_LTE,
                '>' => self::OP_GT,
                '<' => self::OP_LT,
                default => self::OP_EQ,
            };

            // First clause per key wins within a group (parity with frontend search-lite).
            if (! isset($seenKeys[$key])) {
                $clauses[] = [
                    'key' => $key,
                    'op' => $op,
                    'value' => $value,
                ];
                $seenKeys[$key] = true;
            }

            $residual = str_replace($match[0], ' ', $residual);
        }

        return [
            'clauses' => $clauses,
            'residual' => trim(preg_replace('/\s+/', ' ', $residual) ?? $residual),
        ];
    }

    /** @return list<string> */
    private static function splitTopLevelOr(string $raw): array
    {
        $parts = [];
        $buffer = '';
        $inQuotes = false;
        $length = strlen($raw);
        $i = 0;

        while ($i < $length) {
            $ch = $raw[$i];
            if ($ch === '"') {
                $inQuotes = ! $inQuotes;
                $buffer .= $ch;
                $i++;
                continue;
            }

            if (! $inQuotes && preg_match('/^(?:\s+OR\s+)/i', substr($raw, $i), $match) === 1) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $parts[] = $trimmed;
                }
                $buffer = '';
                $i += strlen($match[0]);
                continue;
            }

            $buffer .= $ch;
            $i++;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $parts[] = $trimmed;
        }

        return $parts === [] ? [trim($raw)] : $parts;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function applyClause(Builder $query, array $field, string $op, string $value): void
    {
        if (isset($field['handler']) && is_callable($field['handler'])) {
            ($field['handler'])($query, $op, $value);

            return;
        }

        $type = (string) ($field['type'] ?? 'string');
        $relation = isset($field['relation']) ? (string) $field['relation'] : null;
        $relationColumn = isset($field['relation_column']) ? (string) $field['relation_column'] : null;
        $columns = [];
        if (isset($field['column']) && is_string($field['column']) && $field['column'] !== '') {
            $columns[] = $field['column'];
        }
        if (isset($field['columns']) && is_array($field['columns'])) {
            foreach ($field['columns'] as $column) {
                $columns[] = (string) $column;
            }
        }
        $columns = array_values(array_unique(array_filter($columns, static fn (string $c): bool => $c !== '')));

        $orValues = self::splitOrValues($value);
        if (count($orValues) > 1 && $op === self::OP_NE) {
            // Pipe on != means exclude all listed values (AND / NOT IN), not OR.
            foreach ($orValues as $part) {
                if ($relation !== null && $relationColumn !== null) {
                    self::applyRelationClause($query, $relation, $relationColumn, $op, $part, $type);
                } else {
                    self::applyColumnClause($query, $columns, $op, $part, $type);
                }
            }

            return;
        }

        if (count($orValues) > 1 && in_array($op, [self::OP_EQ, self::OP_CONTAINS], true)) {
            $query->where(function (Builder $group) use ($op, $orValues, $relation, $relationColumn, $columns, $type): void {
                foreach ($orValues as $index => $part) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $group->{$method}(function (Builder $inner) use ($op, $part, $relation, $relationColumn, $columns, $type): void {
                        if ($relation !== null && $relationColumn !== null) {
                            self::applyRelationClause($inner, $relation, $relationColumn, $op, $part, $type);

                            return;
                        }
                        self::applyColumnClause($inner, $columns, $op, $part, $type);
                    });
                }
            });

            return;
        }

        if ($relation !== null && $relationColumn !== null) {
            self::applyRelationClause($query, $relation, $relationColumn, $op, $value, $type);

            return;
        }

        self::applyColumnClause($query, $columns, $op, $value, $type);
    }

    /**
     * @param  list<string>  $columns
     */
    private static function applyColumnClause(Builder $query, array $columns, string $op, string $value, string $type): void
    {
        if ($columns === []) {
            return;
        }

        if (in_array($op, [self::OP_GTE, self::OP_LTE, self::OP_GT, self::OP_LT], true)) {
            $sqlOp = match ($op) {
                self::OP_GTE => '>=',
                self::OP_LTE => '<=',
                self::OP_GT => '>',
                default => '<',
            };
            if (count($columns) === 1) {
                $query->where($columns[0], $sqlOp, $value);
            } else {
                $query->where(function (Builder $outer) use ($columns, $sqlOp, $value): void {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $outer->{$method}($column, $sqlOp, $value);
                    }
                });
            }

            return;
        }

        if ($op === self::OP_NE) {
            if (count($columns) === 1) {
                $query->where(function (Builder $inner) use ($columns, $value): void {
                    $inner->where($columns[0], '!=', $value)->orWhereNull($columns[0]);
                });
            } else {
                $query->where(function (Builder $outer) use ($columns, $value): void {
                    foreach ($columns as $column) {
                        $outer->where(function (Builder $inner) use ($column, $value): void {
                            $inner->where($column, '!=', $value)->orWhereNull($column);
                        });
                    }
                });
            }

            return;
        }

        if ($op === self::OP_CONTAINS || ($op === self::OP_EQ && $type === 'string')) {
            $like = '%'.addcslashes($value, '%_\\').'%';
            $query->where(function (Builder $outer) use ($columns, $like): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $outer->{$method}($column, 'like', $like);
                }
            });

            return;
        }

        // exact equality
        if (count($columns) === 1) {
            $query->where($columns[0], $value);
        } else {
            $query->where(function (Builder $outer) use ($columns, $value): void {
                foreach ($columns as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $outer->{$method}($column, $value);
                }
            });
        }
    }

    private static function applyRelationClause(
        Builder $query,
        string $relation,
        string $relationColumn,
        string $op,
        string $value,
        string $type,
    ): void {
        if ($op === self::OP_NE) {
            $query->where(function (Builder $outer) use ($relation, $relationColumn, $value): void {
                $outer
                    ->whereDoesntHave($relation, static function (Builder $rel) use ($relationColumn, $value): void {
                        $rel->where($relationColumn, $value);
                    })
                    ->orWhereDoesntHave($relation);
            });

            return;
        }

        $like = '%'.addcslashes($value, '%_\\').'%';
        $query->whereHas($relation, static function (Builder $rel) use ($relationColumn, $op, $value, $type, $like): void {
            if ($op === self::OP_CONTAINS || ($op === self::OP_EQ && $type === 'string')) {
                $rel->where($relationColumn, 'like', $like);
            } else {
                $rel->where($relationColumn, $value);
            }
        });
    }

    /** @return list<string> */
    private static function splitOrValues(string $value): array
    {
        if (! str_contains($value, '|')) {
            return [$value];
        }

        $parts = [];
        foreach (explode('|', $value) as $part) {
            $trimmed = trim($part);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        return $parts === [] ? [$value] : array_values(array_unique($parts));
    }

    private static function opToken(string $op): string
    {
        return match ($op) {
            self::OP_NE => '!=',
            self::OP_CONTAINS => '~',
            self::OP_GTE => '>=',
            self::OP_LTE => '<=',
            self::OP_GT => '>',
            self::OP_LT => '<',
            default => ':',
        };
    }

    private static function quoteIfNeeded(string $value): string
    {
        if (str_contains($value, ' ')) {
            return '"'.$value.'"';
        }

        return $value;
    }
}
