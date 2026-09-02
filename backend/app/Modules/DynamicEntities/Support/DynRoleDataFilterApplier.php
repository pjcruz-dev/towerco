<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Applies RoleAccessMatrix data-filter groups onto a DynRecord query.
 */
final class DynRoleDataFilterApplier
{
    /**
     * @param  Builder<\App\Modules\DynamicEntities\Models\DynRecord>  $query
     * @param  list<array{logic: 'and'|'or', rules: list<array{field: string, operator: string, value?: string}>}>  $groups
     */
    public static function apply(Builder $query, string $entityId, array $groups): void
    {
        if ($groups === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($entityId, $groups): void {
            $first = true;
            foreach ($groups as $group) {
                $rules = $group['rules'] ?? [];
                if ($rules === []) {
                    continue;
                }
                $logic = ($group['logic'] ?? 'and') === 'or' ? 'or' : 'and';
                $method = $first ? 'where' : 'orWhere';
                $first = false;
                $outer->{$method}(function (Builder $inner) use ($entityId, $rules, $logic): void {
                    foreach ($rules as $index => $rule) {
                        $field = (string) ($rule['field'] ?? '');
                        $operator = (string) ($rule['operator'] ?? '');
                        $value = (string) ($rule['value'] ?? '');
                        if ($field === '' || $operator === '') {
                            continue;
                        }
                        $useOr = $logic === 'or' && $index > 0;
                        self::applyRule($inner, $entityId, $field, $operator, $value, $useOr);
                    }
                });
            }
        });
    }

    /**
     * @param  Builder<\App\Modules\DynamicEntities\Models\DynRecord>  $query
     */
    private static function applyRule(
        Builder $query,
        string $entityId,
        string $field,
        string $operator,
        string $value,
        bool $or,
    ): void {
        // Built-in columns
        if (in_array($field, ['status', 'title', 'id', 'created_by', 'assigned_user_id', 'created_at', 'updated_at'], true)) {
            self::applyColumnRule($query, $field, $operator, $value, $or);

            return;
        }

        $existsMethod = $or ? 'orWhereExists' : 'whereExists';
        $notExistsMethod = $or ? 'orWhereNotExists' : 'whereNotExists';

        if ($operator === 'is_empty') {
            $query->{$notExistsMethod}(function ($sub) use ($entityId, $field): void {
                $sub->selectRaw('1')
                    ->from('dyn_record_indexes')
                    ->whereColumn('dyn_record_indexes.record_id', 'dyn_records.id')
                    ->where('dyn_record_indexes.entity_id', $entityId)
                    ->where('dyn_record_indexes.field_name', $field)
                    ->where(function ($q): void {
                        $q->whereNotNull('value_string')->where('value_string', '!=', '')
                            ->orWhereNotNull('value_number')
                            ->orWhereNotNull('value_date');
                    });
            });

            return;
        }

        if ($operator === 'is_not_empty') {
            $query->{$existsMethod}(function ($sub) use ($entityId, $field): void {
                $sub->selectRaw('1')
                    ->from('dyn_record_indexes')
                    ->whereColumn('dyn_record_indexes.record_id', 'dyn_records.id')
                    ->where('dyn_record_indexes.entity_id', $entityId)
                    ->where('dyn_record_indexes.field_name', $field)
                    ->where(function ($q): void {
                        $q->where(function ($qq): void {
                            $qq->whereNotNull('value_string')->where('value_string', '!=', '');
                        })->orWhereNotNull('value_number')->orWhereNotNull('value_date');
                    });
            });

            return;
        }

        $query->{$existsMethod}(function ($sub) use ($entityId, $field, $operator, $value): void {
            $sub->selectRaw('1')
                ->from('dyn_record_indexes')
                ->whereColumn('dyn_record_indexes.record_id', 'dyn_records.id')
                ->where('dyn_record_indexes.entity_id', $entityId)
                ->where('dyn_record_indexes.field_name', $field);

            match ($operator) {
                'equals' => $sub->where(function ($q) use ($value): void {
                    $q->where('value_string', $value)
                        ->orWhere('value_number', is_numeric($value) ? $value : null)
                        ->orWhere('value_date', $value);
                }),
                'not_equals' => $sub->where(function ($q) use ($value): void {
                    $q->where(function ($qq) use ($value): void {
                        $qq->whereNull('value_string')->orWhere('value_string', '!=', $value);
                    })->where(function ($qq) use ($value): void {
                        if (is_numeric($value)) {
                            $qq->whereNull('value_number')->orWhere('value_number', '!=', $value);
                        }
                    });
                }),
                'in' => $sub->where(function ($q) use ($value): void {
                    $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
                    if ($parts === []) {
                        return;
                    }
                    $q->whereIn('value_string', $parts);
                    $numeric = array_values(array_filter($parts, static fn (string $p): bool => is_numeric($p)));
                    if ($numeric !== []) {
                        $q->orWhereIn('value_number', $numeric);
                    }
                }),
                'contains' => $sub->where('value_string', 'like', '%'.$value.'%'),
                'not_contains' => $sub->where(function ($q) use ($value): void {
                    $q->whereNull('value_string')->orWhere('value_string', 'not like', '%'.$value.'%');
                }),
                'gt' => $sub->where(function ($q) use ($value): void {
                    if (is_numeric($value)) {
                        $q->where('value_number', '>', $value);
                    } else {
                        $q->where('value_string', '>', $value)->orWhere('value_date', '>', $value);
                    }
                }),
                'gte' => $sub->where(function ($q) use ($value): void {
                    if (is_numeric($value)) {
                        $q->where('value_number', '>=', $value);
                    } else {
                        $q->where('value_string', '>=', $value)->orWhere('value_date', '>=', $value);
                    }
                }),
                'lt' => $sub->where(function ($q) use ($value): void {
                    if (is_numeric($value)) {
                        $q->where('value_number', '<', $value);
                    } else {
                        $q->where('value_string', '<', $value)->orWhere('value_date', '<', $value);
                    }
                }),
                'lte' => $sub->where(function ($q) use ($value): void {
                    if (is_numeric($value)) {
                        $q->where('value_number', '<=', $value);
                    } else {
                        $q->where('value_string', '<=', $value)->orWhere('value_date', '<=', $value);
                    }
                }),
                default => $sub->where('value_string', 'like', '%'.$value.'%'),
            };
        });
    }

    /**
     * @param  Builder<\App\Modules\DynamicEntities\Models\DynRecord>  $query
     */
    private static function applyColumnRule(
        Builder $query,
        string $field,
        string $operator,
        string $value,
        bool $or,
    ): void {
        $column = match ($field) {
            'id' => 'id',
            'status' => 'status',
            'title' => 'title',
            'created_by' => 'created_by',
            'assigned_user_id' => 'assigned_user_id',
            'created_at' => 'created_at',
            'updated_at' => 'updated_at',
            default => null,
        };
        if ($column === null) {
            return;
        }

        $method = $or ? 'orWhere' : 'where';

        match ($operator) {
            'equals' => $query->{$method}($column, $value),
            'not_equals' => $query->{$method}(function (Builder $q) use ($column, $value): void {
                $q->where($column, '!=', $value)->orWhereNull($column);
            }),
            'in' => (static function () use ($query, $method, $column, $value): void {
                $parts = array_values(array_filter(array_map('trim', explode(',', $value))));
                if ($parts === []) {
                    return;
                }
                $query->{$method.'In'}($column, $parts);
            })(),
            'contains' => $query->{$method}($column, 'like', '%'.$value.'%'),
            'not_contains' => $query->{$method}(function (Builder $q) use ($column, $value): void {
                $q->where($column, 'not like', '%'.$value.'%')->orWhereNull($column);
            }),
            'is_empty' => $query->{$method}(function (Builder $q) use ($column): void {
                $q->whereNull($column)->orWhere($column, '');
            }),
            'is_not_empty' => $query->{$method}(function (Builder $q) use ($column): void {
                $q->whereNotNull($column)->where($column, '!=', '');
            }),
            'gt' => $query->{$method}($column, '>', $value),
            'gte' => $query->{$method}($column, '>=', $value),
            'lt' => $query->{$method}($column, '<', $value),
            'lte' => $query->{$method}($column, '<=', $value),
            default => $query->{$method}($column, 'like', '%'.$value.'%'),
        };
    }
}
