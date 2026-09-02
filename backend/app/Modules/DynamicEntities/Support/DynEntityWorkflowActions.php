<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Support;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use Illuminate\Support\Str;

/**
 * Metacoresoft-style Workflow Button actions (WHEN / THEN).
 *
 * Preferred source: entity `workflows` system field `options_json.buttons`.
 * Falls back to built-in packs (BIR 2307).
 */
final class DynEntityWorkflowActions
{
    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }>
     */
    public static function forEntity(DynEntity|string $entityOrSlug): array
    {
        $slug = $entityOrSlug instanceof DynEntity
            ? (string) $entityOrSlug->slug
            : (string) $entityOrSlug;

        $configured = [];
        if ($entityOrSlug instanceof DynEntity) {
            $configured = self::fromWorkflowsField($entityOrSlug);
        } else {
            $entity = DynEntity::query()->where('slug', $slug)->first();
            if ($entity) {
                $configured = self::fromWorkflowsField($entity);
            }
        }

        $managed = [];
        try {
            $managed = app(\App\Modules\DynamicEntities\Services\DynWorkflowService::class)
                ->manualActionsForEntity($slug);
        } catch (\Throwable) {
            $managed = [];
        }

        // Prefer field-configured buttons; append managed workflows with unique ids.
        $used = [];
        $out = [];
        foreach ($configured as $action) {
            $id = (string) ($action['id'] ?? '');
            if ($id === '' || isset($used[$id])) {
                continue;
            }
            $used[$id] = true;
            $out[] = $action;
        }
        foreach ($managed as $action) {
            $id = (string) ($action['id'] ?? '');
            if ($id === '' || isset($used[$id])) {
                continue;
            }
            $used[$id] = true;
            $out[] = $action;
        }

        if ($out !== []) {
            return $out;
        }

        return self::builtinForSlug($slug);
    }

    /**
     * @return list<string>
     */
    public static function statusChoicesFor(DynEntity|string $entityOrSlug): array
    {
        $actions = self::forEntity($entityOrSlug);
        $statuses = [];
        foreach ($actions as $action) {
            foreach ($action['when'] as $cond) {
                if (($cond['field'] ?? '') === 'status') {
                    $statuses[] = $cond['value'];
                }
            }
            foreach ($action['then_updates'] as $upd) {
                if (($upd['field'] ?? '') === 'status') {
                    $statuses[] = $upd['value'];
                }
            }
            $statuses[] = $action['from_status'];
            $statuses[] = $action['to_status'];
        }

        return array_values(array_unique(array_filter($statuses)));
    }

    /**
     * @return array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }|null
     */
    public static function find(DynEntity|string $entityOrSlug, string $actionId): ?array
    {
        foreach (self::forEntity($entityOrSlug) as $action) {
            if ($action['id'] === $actionId) {
                return $action;
            }
        }

        return null;
    }

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }>
     */
    public static function availableForStatus(DynEntity|string $entityOrSlug, ?string $status): array
    {
        return self::availableForRecord($entityOrSlug, $status, []);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return list<array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }>
     */
    public static function availableForRecord(DynEntity|string $entityOrSlug, ?string $status, array $values): array
    {
        return array_values(array_filter(
            self::forEntity($entityOrSlug),
            static fn (array $a): bool => self::matchesWhen($a, $status, $values),
        ));
    }

    /**
     * @param  array{
     *   when: list<array{field: string, op: string, value: string}>,
     *   from_status?: string
     * }  $action
     * @param  array<string, mixed>  $values
     */
    public static function matchesWhen(array $action, ?string $status, array $values): bool
    {
        $statusMatches = $action['status_matches'] ?? null;
        $statusField = (string) ($action['status_field'] ?? 'status');
        $isManaged = isset($action['managed_workflow_id']);

        if (is_array($statusMatches) && $statusMatches !== []) {
            $current = self::fieldValue($statusField, $status, $values);
            $hit = false;
            foreach ($statusMatches as $expected) {
                if (strcasecmp($current, trim((string) $expected)) === 0) {
                    $hit = true;
                    break;
                }
            }
            if (! $hit) {
                return false;
            }
            $when = array_values(array_filter(
                $action['when'] ?? [],
                static fn ($c): bool => is_array($c) && (string) ($c['field'] ?? '') !== $statusField,
            ));
            if ($when === []) {
                return true;
            }
            foreach ($when as $cond) {
                $field = (string) ($cond['field'] ?? 'status');
                $op = (string) ($cond['op'] ?? 'eq');
                $expected = (string) ($cond['value'] ?? '');
                $cur = self::fieldValue($field, $status, $values);
                $ok = strcasecmp($cur, $expected) === 0;
                if ($op === 'neq') {
                    $ok = ! $ok;
                }
                if (! $ok) {
                    return false;
                }
            }

            return true;
        }

        if ($isManaged && is_array($statusMatches) && $statusMatches === []) {
            // Managed workflow with no status constraint: always eligible.
            return true;
        }

        $when = $action['when'] ?? [];
        if ($when === []) {
            $from = trim((string) ($action['from_status'] ?? ''));
            if ($from === '') {
                return false;
            }
            $when = [['field' => 'status', 'op' => 'eq', 'value' => $from]];
        }

        foreach ($when as $cond) {
            $field = (string) ($cond['field'] ?? 'status');
            $op = (string) ($cond['op'] ?? 'eq');
            $expected = (string) ($cond['value'] ?? '');
            $current = self::fieldValue($field, $status, $values);
            $ok = strcasecmp($current, $expected) === 0;
            if ($op === 'neq') {
                $ok = ! $ok;
            }
            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function fieldValue(string $field, ?string $status, array $values): string
    {
        if ($field === 'status') {
            $fromValues = $values['status'] ?? null;
            if (is_scalar($fromValues) && trim((string) $fromValues) !== '') {
                return trim((string) $fromValues);
            }
            $current = trim((string) ($status ?? ''));

            return $current !== '' ? $current : 'Draft';
        }

        $raw = $values[$field] ?? null;
        if ($raw === null) {
            return '';
        }

        return is_scalar($raw) ? trim((string) $raw) : '';
    }

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }>
     */
    public static function builtinForSlug(string $slug): array
    {
        if ($slug !== 'bir_form_2307_certificates') {
            return [];
        }

        $used = [];
        $out = [];
        foreach ([
            [
                'id' => 'post_certificate',
                'label' => 'Post Certificate',
                'from_status' => 'Draft',
                'to_status' => 'Posted',
                'confirm' => null,
                'variant' => 'default',
            ],
            [
                'id' => 'cancel_certificate',
                'label' => 'Cancel Certificate',
                'from_status' => 'Posted',
                'to_status' => 'Cancelled',
                'confirm' => 'Are you sure you want to cancel this certificate?',
                'variant' => 'outline',
            ],
        ] as $row) {
            $normalized = self::normalizeButtonRow($row, $used);
            if ($normalized === null) {
                continue;
            }
            $used[$normalized['id']] = true;
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @return array{buttons: list<array<string, mixed>>}
     */
    public static function defaultOptionsPayload(string $slug): array
    {
        return ['buttons' => self::builtinForSlug($slug)];
    }

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }>
     */
    public static function normalizeButtons(mixed $raw): array
    {
        $list = [];
        if (is_array($raw) && array_is_list($raw)) {
            $list = $raw;
        } elseif (is_array($raw) && isset($raw['buttons']) && is_array($raw['buttons'])) {
            $list = $raw['buttons'];
        }

        $out = [];
        $usedIds = [];
        foreach ($list as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = self::normalizeButtonRow($row, $usedIds);
            if ($normalized === null) {
                continue;
            }
            $usedIds[$normalized['id']] = true;
            $out[] = $normalized;
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $usedIds
     * @return array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, value: string}>
     * }|null
     */
    private static function normalizeButtonRow(array $row, array $usedIds): ?array
    {
        $label = trim((string) ($row['label'] ?? ''));
        if ($label === '') {
            return null;
        }

        $legacyFrom = trim((string) ($row['from_status'] ?? $row['fromStatus'] ?? ''));
        $legacyTo = trim((string) ($row['to_status'] ?? $row['toStatus'] ?? ''));

        $when = self::normalizeWhen($row['when'] ?? null, $legacyFrom);
        $then = self::normalizeThen($row['then_updates'] ?? $row['thenUpdates'] ?? null, $legacyTo);
        if ($when === [] || $then === []) {
            return null;
        }

        $from = $legacyFrom;
        foreach ($when as $cond) {
            if ($cond['field'] === 'status') {
                $from = $cond['value'];
                break;
            }
        }
        if ($from === '') {
            $from = $when[0]['value'];
        }

        $to = $legacyTo;
        foreach ($then as $upd) {
            if ($upd['field'] === 'status' && ($upd['mode'] ?? 'set') === 'set') {
                $to = $upd['value'];
                break;
            }
        }
        if ($to === '') {
            foreach ($then as $upd) {
                if ($upd['field'] === 'status') {
                    $to = $upd['value'];
                    break;
                }
            }
        }
        if ($to === '') {
            $to = $then[0]['value'] ?? '';
        }

        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            $id = Str::snake($label);
        }
        $id = Str::slug($id, '_');
        if ($id === '') {
            return null;
        }
        $base = $id;
        $n = 2;
        while (isset($usedIds[$id])) {
            $id = $base.'_'.$n;
            $n++;
        }

        $variant = strtolower(trim((string) ($row['variant'] ?? 'default')));
        if (! in_array($variant, ['default', 'outline', 'secondary', 'destructive'], true)) {
            $variant = 'default';
        }

        $confirm = $row['confirm'] ?? null;
        $confirm = is_string($confirm) && trim($confirm) !== '' ? trim($confirm) : null;

        $roleIds = [];
        $roleRaw = $row['role_ids'] ?? $row['roleIds'] ?? [];
        if (is_array($roleRaw)) {
            foreach ($roleRaw as $roleId) {
                $roleId = trim((string) $roleId);
                if ($roleId !== '') {
                    $roleIds[] = $roleId;
                }
            }
        }

        return [
            'id' => $id,
            'label' => $label,
            'from_status' => $from,
            'to_status' => $to,
            'confirm' => $confirm,
            'variant' => $variant,
            'role_ids' => $roleIds,
            'when' => $when,
            'loads' => self::normalizeLoads($row['loads'] ?? null),
            'then_updates' => $then,
            'creates' => self::normalizeCreates($row['creates'] ?? null),
            'emails' => self::normalizeEmails($row['emails'] ?? null),
        ];
    }

    /**
     * @return list<array{field: string, op: string, value: string}>
     */
    private static function normalizeWhen(mixed $raw, string $legacyFrom): array
    {
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $field = trim((string) ($row['field'] ?? 'status'));
                $op = strtolower(trim((string) ($row['op'] ?? 'eq')));
                $value = trim((string) ($row['value'] ?? ''));
                if ($field === '' || $value === '') {
                    continue;
                }
                if ($op !== 'neq') {
                    $op = 'eq';
                }
                $out[] = ['field' => $field, 'op' => $op, 'value' => $value];
            }
        }
        if ($out === [] && $legacyFrom !== '') {
            $out[] = ['field' => 'status', 'op' => 'eq', 'value' => $legacyFrom];
        }

        return $out;
    }

    /**
     * @return list<array{target: string, field: string, mode: string, value: string, from?: string}>
     */
    private static function normalizeThen(mixed $raw, string $legacyTo): array
    {
        $out = [];
        if (is_array($raw)) {
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $field = trim((string) ($row['field'] ?? 'status'));
                $mode = strtolower(trim((string) ($row['mode'] ?? 'set')));
                if (! in_array($mode, ['set', 'copy', 'system', 'ask', 'expr'], true)) {
                    $mode = 'set';
                }
                $value = trim((string) ($row['value'] ?? ''));
                $from = trim((string) ($row['from'] ?? ''));
                $target = trim((string) ($row['target'] ?? 'this'));
                if ($target === '') {
                    $target = 'this';
                }
                if ($field === '') {
                    continue;
                }
                if ($mode === 'set' && $value === '') {
                    continue;
                }
                if ($mode === 'copy' && $from === '') {
                    continue;
                }
                if (! in_array($mode, ['set', 'copy'], true)) {
                    // Persist for UI parity; runtime ignores unsupported modes.
                    if ($value === '' && $from === '') {
                        continue;
                    }
                }
                $item = [
                    'target' => $target,
                    'field' => $field,
                    'mode' => $mode,
                    'value' => $value,
                ];
                if ($from !== '') {
                    $item['from'] = $from;
                }
                $out[] = $item;
            }
        }
        if ($out === [] && $legacyTo !== '') {
            $out[] = ['target' => 'this', 'field' => 'status', 'mode' => 'set', 'value' => $legacyTo];
        }

        return $out;
    }

    /**
     * @return list<array{source_field: string, alias: string}>
     */
    private static function normalizeLoads(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $source = trim((string) ($row['source_field'] ?? $row['sourceField'] ?? ''));
            $alias = Str::slug(trim((string) ($row['alias'] ?? '')), '_');
            if ($source === '' || $alias === '') {
                continue;
            }
            $out[] = ['source_field' => $source, 'alias' => $alias];
        }

        return $out;
    }

    /**
     * @return list<array{entity_slug: string, one_per: ?string, mappings: list<array{field: string, mode: string, value: string, from?: string}>}>
     */
    private static function normalizeCreates(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['entity_slug'] ?? $row['entitySlug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $onePer = trim((string) ($row['one_per'] ?? $row['onePer'] ?? ''));
            $mappings = [];
            $mapRaw = $row['mappings'] ?? [];
            if (is_array($mapRaw)) {
                foreach ($mapRaw as $m) {
                    if (! is_array($m)) {
                        continue;
                    }
                    $field = trim((string) ($m['field'] ?? ''));
                    $mode = strtolower(trim((string) ($m['mode'] ?? 'set')));
                    $value = trim((string) ($m['value'] ?? ''));
                    $from = trim((string) ($m['from'] ?? ''));
                    if ($field === '') {
                        continue;
                    }
                    if ($mode === 'copy') {
                        if ($from === '') {
                            continue;
                        }
                    } elseif ($value === '') {
                        continue;
                    } else {
                        $mode = 'set';
                    }
                    $item = ['field' => $field, 'mode' => $mode, 'value' => $value];
                    if ($from !== '') {
                        $item['from'] = $from;
                    }
                    $mappings[] = $item;
                }
            }
            $out[] = [
                'entity_slug' => $slug,
                'one_per' => $onePer !== '' ? Str::slug($onePer, '_') : null,
                'mappings' => $mappings,
            ];
        }

        return $out;
    }

    /**
     * @return list<array{subject: string, to: string, body: string}>
     */
    private static function normalizeEmails(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $to = trim((string) ($row['to'] ?? ''));
            $templateSlug = trim((string) ($row['template_slug'] ?? ''));
            if ($to === '' && $templateSlug === '') {
                continue;
            }
            $item = [
                'subject' => trim((string) ($row['subject'] ?? '')) ?: 'Workflow notification',
                'to' => $to,
                'body' => trim((string) ($row['body'] ?? '')),
            ];
            if ($templateSlug !== '') {
                $item['template_slug'] = $templateSlug;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @return list<array{
     *   id: string,
     *   label: string,
     *   from_status: string,
     *   to_status: string,
     *   confirm?: string|null,
     *   variant?: string,
     *   role_ids?: list<string>,
     *   when: list<array{field: string, op: string, value: string}>,
     *   then_updates: list<array{target: string, field: string, mode: string, value: string, from?: string}>
     * }>
     */
    private static function fromWorkflowsField(DynEntity $entity): array
    {
        $entity->loadMissing('fields');
        /** @var DynField|null $field */
        $field = $entity->fields->first(
            static fn (DynField $f): bool => $f->name === 'workflows',
        );
        if ($field === null) {
            return [];
        }

        return self::normalizeButtons($field->options_json);
    }
}
