<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

/**
 * Restricted Entity Hook DSL (no PHP eval).
 *
 * Shape:
 * {
 *   "when": [{ "field": "subsidiary_id", "op": "filled" }],
 *   "actions": [{ "type": "mirror_field", "from": "subsidiary_id", "to": "company_id" }]
 * }
 */
final class DynEntityHookDsl
{
    /** @var list<string> */
    public const EVENTS = [
        'before_create',
        'after_create',
        'before_update',
        'after_update',
        'before_delete',
        'after_delete',
        'before_action',
    ];

    /** @var list<string> */
    public const WHEN_OPS = ['eq', 'neq', 'filled', 'empty', 'changed'];

    /** @var list<string> */
    public const ACTION_TYPES = ['mirror_field', 'set_field', 'clear_field'];

    /**
     * @param  array<string, mixed>|null  $raw
     * @return array{when: list<array{field: string, op: string, value?: string}>, actions: list<array<string, mixed>>}
     */
    public static function normalize(?array $raw): array
    {
        $when = [];
        $actions = [];

        if (is_array($raw)) {
            foreach (($raw['when'] ?? []) as $rule) {
                if (! is_array($rule)) {
                    continue;
                }
                $field = trim((string) ($rule['field'] ?? ''));
                $op = strtolower(trim((string) ($rule['op'] ?? 'eq')));
                if ($field === '' || ! in_array($op, self::WHEN_OPS, true)) {
                    continue;
                }
                $item = ['field' => $field, 'op' => $op];
                if (array_key_exists('value', $rule)) {
                    $item['value'] = (string) $rule['value'];
                }
                $when[] = $item;
            }

            foreach (($raw['actions'] ?? []) as $action) {
                if (! is_array($action)) {
                    continue;
                }
                $type = strtolower(trim((string) ($action['type'] ?? '')));
                if (! in_array($type, self::ACTION_TYPES, true)) {
                    continue;
                }
                if ($type === 'mirror_field') {
                    $from = trim((string) ($action['from'] ?? ''));
                    $to = trim((string) ($action['to'] ?? ''));
                    if ($from === '' || $to === '') {
                        continue;
                    }
                    $actions[] = ['type' => 'mirror_field', 'from' => $from, 'to' => $to];
                    continue;
                }
                if ($type === 'set_field') {
                    $field = trim((string) ($action['field'] ?? ''));
                    if ($field === '') {
                        continue;
                    }
                    $actions[] = [
                        'type' => 'set_field',
                        'field' => $field,
                        'value' => array_key_exists('value', $action) ? $action['value'] : null,
                    ];
                    continue;
                }
                if ($type === 'clear_field') {
                    $field = trim((string) ($action['field'] ?? ''));
                    if ($field === '') {
                        continue;
                    }
                    $actions[] = ['type' => 'clear_field', 'field' => $field];
                }
            }
        }

        return ['when' => $when, 'actions' => $actions];
    }

    /**
     * @param  list<string>|mixed  $events
     * @return list<string>
     */
    public static function normalizeEvents(mixed $events): array
    {
        if (! is_array($events)) {
            return [];
        }
        $out = [];
        foreach ($events as $event) {
            $name = strtolower(trim((string) $event));
            if ($name !== '' && in_array($name, self::EVENTS, true) && ! in_array($name, $out, true)) {
                $out[] = $name;
            }
        }

        return $out;
    }

    /**
     * @param  array{when: list<array{field: string, op: string, value?: string}>, actions: list<array<string, mixed>>}  $definition
     * @param  array<string, mixed>  $values
     * @param  array<string, mixed>|null  $beforeValues  previous values (update only)
     */
    public static function matchesWhen(array $definition, array $values, ?array $beforeValues = null): bool
    {
        $rules = $definition['when'] ?? [];
        if ($rules === []) {
            return true;
        }

        foreach ($rules as $rule) {
            $field = (string) ($rule['field'] ?? '');
            $op = (string) ($rule['op'] ?? 'eq');
            $expected = (string) ($rule['value'] ?? '');
            $curr = self::scalar($values[$field] ?? null);
            $prev = $beforeValues !== null ? self::scalar($beforeValues[$field] ?? null) : null;

            $ok = match ($op) {
                'filled' => $curr !== '',
                'empty' => $curr === '',
                'eq' => strcasecmp($curr, $expected) === 0,
                'neq' => strcasecmp($curr, $expected) !== 0,
                'changed' => $beforeValues !== null && strcasecmp((string) $prev, $curr) !== 0,
                default => false,
            };
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply actions onto a values map (returns new map).
     *
     * @param  array{when: list<array{field: string, op: string, value?: string}>, actions: list<array<string, mixed>>}  $definition
     * @param  array<string, mixed>  $values
     * @param  array{actor_id?: string|null, record_id?: string|null}  $ctx
     * @return array<string, mixed>
     */
    public static function apply(array $definition, array $values, array $ctx = []): array
    {
        $out = $values;
        foreach ($definition['actions'] ?? [] as $action) {
            $type = (string) ($action['type'] ?? '');
            if ($type === 'mirror_field') {
                $from = (string) ($action['from'] ?? '');
                $to = (string) ($action['to'] ?? '');
                if ($from !== '' && $to !== '') {
                    $out[$to] = $out[$from] ?? null;
                }
                continue;
            }
            if ($type === 'set_field') {
                $field = (string) ($action['field'] ?? '');
                if ($field === '') {
                    continue;
                }
                $out[$field] = self::resolveToken($action['value'] ?? null, $ctx);
                continue;
            }
            if ($type === 'clear_field') {
                $field = (string) ($action['field'] ?? '');
                if ($field !== '') {
                    $out[$field] = null;
                }
            }
        }

        return $out;
    }

    /**
     * @param  array{actor_id?: string|null, record_id?: string|null}  $ctx
     */
    private static function resolveToken(mixed $value, array $ctx): mixed
    {
        if (! is_string($value)) {
            return $value;
        }
        $trimmed = trim($value);
        return match ($trimmed) {
            '$now' => now()->toIso8601String(),
            '$actor_id' => $ctx['actor_id'] ?? null,
            '$record_id' => $ctx['record_id'] ?? null,
            default => $value,
        };
    }

    private static function scalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return implode(',', array_map('strval', $value));
        }

        return trim((string) $value);
    }
}
