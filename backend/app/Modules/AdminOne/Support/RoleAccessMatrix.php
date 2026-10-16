<?php

declare(strict_types=1);

namespace App\Modules\AdminOne\Support;

/**
 * Metacoresoft-style per-entity / field / workflow ACL stored on tenant roles.
 *
 * @phpstan-type EntityAccess array{view?: bool, view_own?: bool, create?: bool, edit?: bool, delete?: bool, export?: bool, import?: bool, smart_scan?: bool, undo?: bool, truncate?: bool, duplicate?: bool, columns?: bool}
 * @phpstan-type FieldAccess 'full'|'view'|'table_record'|'record'|'form'|'hide'
 * @phpstan-type WorkflowAccess array<string, bool>
 * @phpstan-type DataFilterRule array{field: string, operator: string, value?: string}
 * @phpstan-type DataFilterGroup array{logic: 'and'|'or', rules: list<DataFilterRule>}
 * @phpstan-type AccessMatrix array{
 *   entities?: array<string, EntityAccess>,
 *   fields?: array<string, array<string, FieldAccess>>,
 *   workflows?: array<string, WorkflowAccess>,
 *   filters?: array<string, DataFilterGroup>,
 *   statuses?: array<string, list<string>>,
 *   ai?: array{preset: string, capabilities?: array<string, bool>, instructions?: string}
 * }
 */
final class RoleAccessMatrix
{
    public const FIELD_LEVELS = ['full', 'view', 'table_record', 'record', 'form', 'hide'];

    /** Power columns. When a stored row omits the key, it follows the base action. */
    public const POWER_FLAGS = [
        'import' => 'create',
        'smart_scan' => 'create',
        'undo' => 'edit',
        'truncate' => 'delete',
        'duplicate' => 'create',
        'columns' => 'view',
    ];

    public const AI_PRESETS = ['unrestricted', 'none', 'ask', 'ask_data', 'builder', 'full', 'custom'];

    public const AI_CAPABILITIES = [
        'ask_lookup',
        'create_update',
        'delete_records',
        'build_reports',
        'build_system',
        'workflows',
        'users_roles',
        'read_code',
    ];

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
                foreach (self::POWER_FLAGS as $flag => $_base) {
                    if (array_key_exists($flag, $row)) {
                        $entities[$slug][$flag] = (bool) $row[$flag];
                    }
                }
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

        $statuses = self::normalizeStatuses($raw['statuses'] ?? null);
        if ($statuses !== []) {
            $out['statuses'] = $statuses;
        }

        $ai = self::normalizeAi($raw['ai'] ?? null);
        if ($ai !== null) {
            $out['ai'] = $ai;
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
                foreach (self::POWER_FLAGS as $flag => $base) {
                    $explicit = array_key_exists($flag, $row) ? (bool) $row[$flag] : (bool) ($row[$base] ?? false);
                    $current[$flag] = ($current[$flag] ?? false) || $explicit;
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

        $statuses = self::mergeStatuses($matrices);
        if ($statuses !== []) {
            $out['statuses'] = $statuses;
        }

        $ai = self::mergeAi($matrices);
        if ($ai !== null) {
            $out['ai'] = $ai;
        }

        return $out;
    }

    /**
     * A slug stays restricted only when every role lists it. Values are the union.
     *
     * @param  list<AccessMatrix>  $matrices
     * @return array<string, list<string>>
     */
    private static function mergeStatuses(array $matrices): array
    {
        if ($matrices === []) {
            return [];
        }
        $sets = [];
        foreach ($matrices as $matrix) {
            $statuses = $matrix['statuses'] ?? null;
            if (! is_array($statuses) || $statuses === []) {
                return [];
            }
            $sets[] = $statuses;
        }
        $slugs = array_keys($sets[0]);
        foreach ($sets as $statuses) {
            $slugs = array_values(array_intersect($slugs, array_keys($statuses)));
        }
        $out = [];
        foreach ($slugs as $slug) {
            $values = [];
            foreach ($sets as $statuses) {
                foreach ($statuses[$slug] as $value) {
                    if (is_string($value) && $value !== '') {
                        $values[$value] = $value;
                    }
                }
            }
            $out[$slug] = array_values($values);
        }

        return $out;
    }

    /**
     * Least restrictive wins. One unrestricted role drops the limit.
     *
     * @param  list<AccessMatrix>  $matrices
     * @return array{preset: string, capabilities?: array<string, bool>, instructions?: string}|null
     */
    private static function mergeAi(array $matrices): ?array
    {
        $capabilities = array_fill_keys(self::AI_CAPABILITIES, false);
        $notes = [];
        $sawLimit = false;
        foreach ($matrices as $matrix) {
            $ai = $matrix['ai'] ?? null;
            if (! is_array($ai) || ($ai['preset'] ?? 'unrestricted') === 'unrestricted') {
                return null;
            }
            $sawLimit = true;
            $resolved = self::capabilitiesForPreset((string) $ai['preset'], is_array($ai['capabilities'] ?? null) ? $ai['capabilities'] : []);
            foreach (self::AI_CAPABILITIES as $cap) {
                $capabilities[$cap] = $capabilities[$cap] || $resolved[$cap];
            }
            $note = trim((string) ($ai['instructions'] ?? ''));
            if ($note !== '') {
                $notes[$note] = $note;
            }
        }
        if (! $sawLimit) {
            return null;
        }
        $ai = [
            'preset' => 'custom',
            'capabilities' => $capabilities,
        ];
        if ($notes !== []) {
            $ai['instructions'] = implode("\n", array_values($notes));
        }

        return $ai;
    }

    /**
     * @param  array<string, bool>  $custom
     * @return array<string, bool>
     */
    public static function capabilitiesForPreset(string $preset, array $custom = []): array
    {
        $all = array_fill_keys(self::AI_CAPABILITIES, true);
        $none = array_fill_keys(self::AI_CAPABILITIES, false);

        return match ($preset) {
            'none' => $none,
            'ask' => array_replace($none, ['ask_lookup' => true]),
            'ask_data' => array_replace($none, ['ask_lookup' => true, 'create_update' => true]),
            'builder' => array_replace($all, [
                'delete_records' => false,
                'users_roles' => false,
                'read_code' => false,
            ]),
            'custom' => array_replace($none, array_intersect_key(array_map('boolval', $custom), $none)),
            default => $all,
        };
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

        if (isset(self::POWER_FLAGS[$action]) && ! array_key_exists($action, $row)) {
            $base = self::POWER_FLAGS[$action];

            return (bool) ($row[$base] ?? false);
        }

        return (bool) ($row[$action] ?? false);
    }

    /**
     * Allowed status values, or null when the role does not restrict this entity.
     *
     * @param  AccessMatrix  $matrix
     * @return list<string>|null
     */
    public static function allowedStatuses(array $matrix, string $slug): ?array
    {
        $statuses = $matrix['statuses'] ?? null;
        if (! is_array($statuses) || ! array_key_exists($slug, $statuses) || ! is_array($statuses[$slug])) {
            return null;
        }

        return array_values(array_filter($statuses[$slug], static fn ($value): bool => is_string($value) && $value !== ''));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function normalizeStatuses(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $slug => $values) {
            if (! is_string($slug) || $slug === '' || ! is_array($values)) {
                continue;
            }
            $clean = [];
            foreach ($values as $value) {
                if (! is_scalar($value)) {
                    continue;
                }
                $text = trim((string) $value);
                if ($text === '' || strlen($text) > 64) {
                    continue;
                }
                $clean[$text] = $text;
                if (count($clean) >= 100) {
                    break;
                }
            }
            $out[$slug] = array_values($clean);
        }

        return $out;
    }

    /**
     * @return array{preset: string, capabilities?: array<string, bool>, instructions?: string}|null
     */
    private static function normalizeAi(mixed $raw): ?array
    {
        if (! is_array($raw)) {
            return null;
        }
        $preset = trim((string) ($raw['preset'] ?? 'unrestricted'));
        if (! in_array($preset, self::AI_PRESETS, true)) {
            $preset = 'unrestricted';
        }
        $capabilities = [];
        $rawCaps = $raw['capabilities'] ?? null;
        if (is_array($rawCaps)) {
            foreach (self::AI_CAPABILITIES as $cap) {
                if (array_key_exists($cap, $rawCaps)) {
                    $capabilities[$cap] = (bool) $rawCaps[$cap];
                }
            }
        }
        $instructions = trim((string) ($raw['instructions'] ?? ''));
        if (strlen($instructions) > 2000) {
            $instructions = substr($instructions, 0, 2000);
        }
        if ($preset === 'unrestricted' && $capabilities === [] && $instructions === '') {
            return null;
        }
        $ai = ['preset' => $preset];
        if ($capabilities !== []) {
            $ai['capabilities'] = $capabilities;
        }
        if ($instructions !== '') {
            $ai['instructions'] = $instructions;
        }

        return $ai;
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
     * form: full, view, form.
     * table: full, view, table_record.
     * record: full, view, table_record, record.
     */
    public static function visibleOn(string $level, string $surface): bool
    {
        return match ($surface) {
            'form' => in_array($level, ['full', 'view', 'form'], true),
            'table' => in_array($level, ['full', 'view', 'table_record'], true),
            'record' => in_array($level, ['full', 'view', 'table_record', 'record'], true),
            default => false,
        };
    }

    public static function editableOnForm(string $level): bool
    {
        return $level === 'full' || $level === 'form';
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
