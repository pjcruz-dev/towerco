<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Support;

/**
 * Metacoresoft-style per-entity / field / workflow ACL stored on tenant roles.
 *
 * @phpstan-type EntityAccess array{view?: bool, view_own?: bool, create?: bool, edit?: bool, delete?: bool, export?: bool}
 * @phpstan-type FieldAccess 'full'|'view'|'table_record'|'record'|'form'|'hide'
 * @phpstan-type WorkflowAccess array<string, bool>
 * @phpstan-type DataFilterRule array{field: string, operator: string, value?: string}
 * @phpstan-type DataFilterGroup array{logic: 'and'|'or', rules: list<DataFilterRule>}
 * @phpstan-type AccessMatrix array{
 *   entities?: array<string, EntityAccess>,
 *   fields?: array<string, array<string, FieldAccess>>,
 *   workflows?: array<string, WorkflowAccess>,
 *   filters?: array<string, DataFilterGroup>
 * }
 */
final class RoleAccessMatrix
{
    public const FIELD_LEVELS = ['full', 'view', 'table_record', 'record', 'form', 'hide'];

    public const FILTER_OPERATORS = [
        'equals',
        'not_equals',
        'contains',
        'not_contains',
        'is_empty',
        'is_not_empty',
        'gt',
        'gte',
        'lt',
        'lte',
    ];

    /**
     * @param  array<string, mixed>|null  $raw
     * @return AccessMatrix
     */
    public static function normalize(?array $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $out = [];

        if (isset($raw['entities']) && is_array($raw['entities'])) {
            $entities = [];
            foreach ($raw['entities'] as $slug => $row) {
                if (! is_string($slug) || $slug === '' || ! is_array($row)) {
                    continue;
                }
                $entities[$slug] = [
                    'view' => (bool) ($row['view'] ?? false),
                    'view_own' => (bool) ($row['view_own'] ?? false),
                    'create' => (bool) ($row['create'] ?? false),
                    'edit' => (bool) ($row['edit'] ?? false),
                    'delete' => (bool) ($row['delete'] ?? false),
                    'export' => (bool) ($row['export'] ?? false),
                ];
                if ($entities[$slug]['view']) {
                    $entities[$slug]['view_own'] = false;
                }
            }
            $out['entities'] = $entities;
        }

        if (isset($raw['fields']) && is_array($raw['fields'])) {
            $fields = [];
            foreach ($raw['fields'] as $slug => $map) {
                if (! is_string($slug) || ! is_array($map)) {
                    continue;
                }
                $fieldMap = [];
                foreach ($map as $field => $level) {
                    if (! is_string($field) || ! is_string($level)) {
                        continue;
                    }
                    if (! in_array($level, self::FIELD_LEVELS, true)) {
                        continue;
                    }
                    $fieldMap[$field] = $level;
                }
                if ($fieldMap !== []) {
                    $fields[$slug] = $fieldMap;
                }
            }
            $out['fields'] = $fields;
        }

        if (isset($raw['workflows']) && is_array($raw['workflows'])) {
            $workflows = [];
            foreach ($raw['workflows'] as $slug => $actions) {
                if (! is_string($slug) || ! is_array($actions)) {
                    continue;
                }
                $actionMap = [];
                foreach ($actions as $action => $enabled) {
                    if (! is_string($action)) {
                        continue;
                    }
                    $actionMap[$action] = (bool) $enabled;
                }
                $workflows[$slug] = $actionMap;
            }
            $out['workflows'] = $workflows;
        }

        if (isset($raw['filters']) && is_array($raw['filters'])) {
            $filters = [];
            foreach ($raw['filters'] as $slug => $config) {
                if (! is_string($slug) || $slug === '' || ! is_array($config)) {
                    continue;
                }
                $normalized = self::normalizeFilterGroup($config);
                if ($normalized !== null) {
                    $filters[$slug] = $normalized;
                }
            }
            if ($filters !== []) {
                $out['filters'] = $filters;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return DataFilterGroup|null
     */
    public static function normalizeFilterGroup(array $config): ?array
    {
        $logic = ($config['logic'] ?? 'and') === 'or' ? 'or' : 'and';
        $rawRules = $config['rules'] ?? null;
        if (! is_array($rawRules)) {
            return null;
        }
        $rules = [];
        foreach ($rawRules as $rule) {
            if (! is_array($rule)) {
                continue;
            }
            $field = trim((string) ($rule['field'] ?? ''));
            $operator = trim((string) ($rule['operator'] ?? ''));
            if ($field === '' || ! in_array($operator, self::FILTER_OPERATORS, true)) {
                continue;
            }
            $value = isset($rule['value']) && is_scalar($rule['value']) ? trim((string) $rule['value']) : '';
            $rules[] = [
                'field' => $field,
                'operator' => $operator,
                'value' => $value,
            ];
        }
        if ($rules === []) {
            return null;
        }

        return ['logic' => $logic, 'rules' => $rules];
    }

    /**
     * Merge matrices from multiple roles (OR for entity/workflow flags; most-restrictive field wins).
     * Filters: unrestricted if any role grants entity access without filters; otherwise keep per-role groups for OR apply.
     *
     * @param  list<AccessMatrix>  $matrices
     * @return AccessMatrix
     */
    public static function merge(array $matrices): array
    {
        $entities = [];
        $fields = [];
        $workflows = [];
        $hasEntityRules = false;
        /** @var array<string, list<DataFilterGroup>> $filterGroups */
        $filterGroups = [];
        /** @var array<string, true> $unrestrictedFilterSlugs */
        $unrestrictedFilterSlugs = [];

        foreach ($matrices as $matrix) {
            if (($matrix['entities'] ?? []) !== []) {
                $hasEntityRules = true;
            }
            foreach ($matrix['entities'] ?? [] as $slug => $row) {
                $current = $entities[$slug] ?? [
                    'view' => false,
                    'view_own' => false,
                    'create' => false,
                    'edit' => false,
                    'delete' => false,
                    'export' => false,
                ];
                foreach (['view', 'view_own', 'create', 'edit', 'delete', 'export'] as $key) {
                    $current[$key] = $current[$key] || (bool) ($row[$key] ?? false);
                }
                if ($current['view']) {
                    $current['view_own'] = false;
                }
                $entities[$slug] = $current;

                $canView = (bool) ($row['view'] ?? false) || (bool) ($row['view_own'] ?? false);
                if ($canView) {
                    $roleFilters = $matrix['filters'][$slug] ?? null;
                    if (! is_array($roleFilters) || ($roleFilters['rules'] ?? []) === []) {
                        $unrestrictedFilterSlugs[$slug] = true;
                    } else {
                        $normalized = self::normalizeFilterGroup($roleFilters);
                        if ($normalized !== null) {
                            $filterGroups[$slug][] = $normalized;
                        }
                    }
                }
            }
            foreach ($matrix['fields'] ?? [] as $slug => $map) {
                foreach ($map as $field => $level) {
                    $prev = $fields[$slug][$field] ?? 'full';
                    $fields[$slug][$field] = self::stricterFieldLevel($prev, $level);
                }
            }
            foreach ($matrix['workflows'] ?? [] as $slug => $actions) {
                foreach ($actions as $action => $enabled) {
                    $workflows[$slug][$action] = ($workflows[$slug][$action] ?? false) || $enabled;
                }
            }
            // Filters on slugs with no entity row still attach if present (custom roles).
            foreach ($matrix['filters'] ?? [] as $slug => $config) {
                if (isset($unrestrictedFilterSlugs[$slug])) {
                    continue;
                }
                if (isset($matrix['entities'][$slug])) {
                    continue; // already handled above
                }
                $normalized = self::normalizeFilterGroup(is_array($config) ? $config : []);
                if ($normalized !== null) {
                    $filterGroups[$slug][] = $normalized;
                }
            }
        }

        $out = [];
        if ($hasEntityRules) {
            $out['entities'] = $entities;
        }
        if ($fields !== []) {
            $out['fields'] = $fields;
        }
        if ($workflows !== []) {
            $out['workflows'] = $workflows;
        }

        $filtersOut = [];
        foreach ($filterGroups as $slug => $groups) {
            if (isset($unrestrictedFilterSlugs[$slug])) {
                continue;
            }
            if (count($groups) === 1) {
                $filtersOut[$slug] = $groups[0];
                continue;
            }
            // Multiple roles: OR the groups by flattening with logic=or only when each group is single-rule;
            // otherwise store first group and note — use groups wrapper in apply layer via _groups.
            $filtersOut[$slug] = [
                'logic' => 'or',
                'rules' => [],
                '_groups' => $groups,
            ];
        }
        if ($filtersOut !== []) {
            $out['filters'] = $filtersOut;
        }

        return $out;
    }

    public static function stricterFieldLevel(string $a, string $b): string
    {
        $rank = array_flip(self::FIELD_LEVELS);

        return ($rank[$a] ?? 0) >= ($rank[$b] ?? 0) ? $a : $b;
    }

    /**
     * @param  AccessMatrix  $matrix
     */
    public static function entityAllows(array $matrix, string $slug, string $action): ?bool
    {
        $entities = $matrix['entities'] ?? null;
        if (! is_array($entities) || $entities === []) {
            return null;
        }
        $row = $entities[$slug] ?? null;
        if (! is_array($row)) {
            return false;
        }
        if ($action === 'view') {
            return (bool) ($row['view'] ?? false) || (bool) ($row['view_own'] ?? false);
        }

        return (bool) ($row[$action] ?? false);
    }

    /**
     * @param  AccessMatrix  $matrix
     */
    public static function fieldLevel(array $matrix, string $slug, string $field): string
    {
        $level = $matrix['fields'][$slug][$field] ?? null;

        return is_string($level) && in_array($level, self::FIELD_LEVELS, true) ? $level : 'full';
    }

    /**
     * @param  AccessMatrix  $matrix
     * @return list<DataFilterGroup>
     */
    public static function filterGroupsFor(array $matrix, string $slug): array
    {
        $config = $matrix['filters'][$slug] ?? null;
        if (! is_array($config)) {
            return [];
        }
        if (isset($config['_groups']) && is_array($config['_groups'])) {
            $groups = [];
            foreach ($config['_groups'] as $group) {
                if (! is_array($group)) {
                    continue;
                }
                $normalized = self::normalizeFilterGroup($group);
                if ($normalized !== null) {
                    $groups[] = $normalized;
                }
            }

            return $groups;
        }
        $normalized = self::normalizeFilterGroup($config);

        return $normalized !== null ? [$normalized] : [];
    }

    /**
     * True when matrix grants view_own without full view for this slug.
     *
     * @param  AccessMatrix  $matrix
     */
    public static function viewOwnOnly(array $matrix, string $slug): bool
    {
        $entities = $matrix['entities'] ?? null;
        if (! is_array($entities) || $entities === []) {
            return false;
        }
        $row = $entities[$slug] ?? null;
        if (! is_array($row)) {
            return false;
        }

        return ! (bool) ($row['view'] ?? false) && (bool) ($row['view_own'] ?? false);
    }
}
