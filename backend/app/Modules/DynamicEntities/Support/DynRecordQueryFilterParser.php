<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use App\Modules\DynamicEntities\Models\DynEntity;
use Illuminate\Http\Request;

/**
 * Parses Metacoresoft-style list filters from query string into RoleAccessMatrix-compatible rules.
 *
 * Supported:
 * - field[gte]= / field_from=
 * - field[lte]= / field_to=
 * - field[in]=a,b / field_in=a,b
 * - field[not]=x / field_not=x
 * - field[eq]=x / bare field=x (equals for builtins; contains for indexed text)
 */
final class DynRecordQueryFilterParser
{
    /** @var list<string> */
    private const RESERVED = [
        'search',
        'status',
        'sort',
        'per_page',
        'page',
        'parent_record_id',
        'foreign_field',
        'filter',
        'api_key',
        'module_pack',
        'active_only',
    ];

    /**
     * @return list<array{field: string, operator: string, value: string}>
     */
    public static function fromRequest(Request $request, DynEntity $entity): array
    {
        $allowed = self::allowedFields($entity);
        $rules = [];

        foreach ($request->query() as $key => $value) {
            $key = (string) $key;
            if (in_array($key, self::RESERVED, true)) {
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $opKey => $opValue) {
                    $op = self::normalizeBracketOp((string) $opKey);
                    if ($op === null || ! self::isAllowedField($key, $allowed)) {
                        continue;
                    }
                    foreach (self::expandRule($key, $op, $opValue) as $rule) {
                        $rules[] = $rule;
                    }
                }

                continue;
            }

            if (preg_match('/^([a-z][a-z0-9_]{0,63})_(from|to|in|not|gte|lte|eq)$/i', $key, $m) === 1) {
                $field = strtolower($m[1]);
                $suffix = strtolower($m[2]);
                $op = match ($suffix) {
                    'from', 'gte' => 'gte',
                    'to', 'lte' => 'lte',
                    'in' => 'in',
                    'not' => 'not_equals',
                    'eq' => 'equals',
                    default => null,
                };
                if ($op === null || ! self::isAllowedField($field, $allowed)) {
                    continue;
                }
                foreach (self::expandRule($field, $op, $value) as $rule) {
                    $rules[] = $rule;
                }

                continue;
            }

            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $key) !== 1) {
                continue;
            }
            if (! self::isAllowedField($key, $allowed)) {
                continue;
            }

            $defaultOp = in_array($key, ['status', 'title', 'id', 'created_by', 'assigned_user_id'], true)
                ? 'equals'
                : 'contains';
            foreach (self::expandRule($key, $defaultOp, $value) as $rule) {
                $rules[] = $rule;
            }
        }

        // Nested filter[field]=value already handled by DynRecordService; also accept filter[field][gte]=
        $nested = $request->query('filter');
        if (is_array($nested)) {
            foreach ($nested as $fieldName => $raw) {
                $fieldName = (string) $fieldName;
                if (! self::isAllowedField($fieldName, $allowed)) {
                    continue;
                }
                if (is_array($raw)) {
                    foreach ($raw as $opKey => $opValue) {
                        $op = self::normalizeBracketOp((string) $opKey);
                        if ($op === null) {
                            continue;
                        }
                        foreach (self::expandRule($fieldName, $op, $opValue) as $rule) {
                            $rules[] = $rule;
                        }
                    }
                }
            }
        }

        return $rules;
    }

    /**
     * @return array<string, true>
     */
    private static function allowedFields(DynEntity $entity): array
    {
        $allowed = [
            'status' => true,
            'title' => true,
            'id' => true,
            'created_by' => true,
            'assigned_user_id' => true,
            'created_at' => true,
            'updated_at' => true,
        ];

        $entity->loadMissing('fields');
        foreach ($entity->fields as $field) {
            $name = (string) $field->name;
            if ($name === '' || (bool) $field->is_system_field) {
                continue;
            }
            // Allow all custom fields so relationship picker filters (and API callers)
            // can constrain lookups even when the column is not shown as a list filter.
            $allowed[$name] = true;
        }

        return $allowed;
    }

    /**
     * @param  array<string, true>  $allowed
     */
    private static function isAllowedField(string $field, array $allowed): bool
    {
        return isset($allowed[$field]);
    }

    private static function normalizeBracketOp(string $op): ?string
    {
        return match (strtolower($op)) {
            'gte', 'from' => 'gte',
            'lte', 'to' => 'lte',
            'gt' => 'gt',
            'lt' => 'lt',
            'in' => 'in',
            'not', 'ne', 'neq' => 'not_equals',
            'eq', 'equals' => 'equals',
            'contains' => 'contains',
            default => null,
        };
    }

    /**
     * @return list<array{field: string, operator: string, value: string}>
     */
    private static function expandRule(string $field, string $operator, mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if ($operator === 'in') {
            $parts = is_array($raw)
                ? $raw
                : (preg_split('/\s*,\s*/', (string) $raw) ?: []);
            $parts = array_values(array_filter(array_map(
                static fn ($v): string => trim((string) $v),
                $parts,
            ), static fn (string $v): bool => $v !== ''));

            if ($parts === []) {
                return [];
            }

            return [[
                'field' => $field,
                'operator' => 'in',
                'value' => implode(',', $parts),
            ]];
        }

        return [[
            'field' => $field,
            'operator' => $operator,
            'value' => is_scalar($raw) ? (string) $raw : json_encode($raw),
        ]];
    }
}
