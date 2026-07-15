<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;

final class EApprovalFormWorkspaceDashboardSupport
{
    /** @var list<string> */
    public const WIDGET_TYPES = ['kpis', 'status_chart', 'recent_activity', 'audit_log', 'submissions_table'];

    /** @var list<string> */
    public const SYSTEM_COLUMN_KEYS = ['document_no', 'status', 'requestor', 'current_step', 'created_at'];

    /** @var list<string> */
    public const SKIP_FIELD_TYPES = [
        'section',
        'page_break',
        'divider',
        'info',
        'heading',
        'html',
        'file',
        'attachment',
        'signature',
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaultDashboard(?EApprovalForm $form = null): array
    {
        return [
            'widgets' => [
                ['id' => 'kpis', 'type' => 'kpis', 'enabled' => true, 'order' => 1],
                ['id' => 'status_chart', 'type' => 'status_chart', 'enabled' => true, 'order' => 2],
                ['id' => 'recent_activity', 'type' => 'recent_activity', 'enabled' => true, 'order' => 3],
                ['id' => 'audit_log', 'type' => 'audit_log', 'enabled' => false, 'order' => 4],
                ['id' => 'submissions_table', 'type' => 'submissions_table', 'enabled' => true, 'order' => 5],
            ],
            'table_columns' => self::defaultTableColumns($form),
            'saved_views' => [
                ['id' => 'all', 'label' => 'All', 'status' => 'all', 'order' => 1],
                ['id' => 'pending', 'label' => 'Pending', 'status' => 'pending', 'order' => 2],
                ['id' => 'returned', 'label' => 'Needs revision', 'status' => 'returned', 'order' => 3],
                ['id' => 'mine', 'label' => 'Mine', 'mine' => true, 'order' => 4],
                ['id' => 'this_month', 'label' => 'This month', 'period_days' => 30, 'order' => 5],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $dashboard
     * @return array<string, mixed>
     */
    public static function normalizeDashboard(?array $dashboard, ?EApprovalForm $form = null): array
    {
        $defaults = self::defaultDashboard($form);
        if (! is_array($dashboard)) {
            return $defaults;
        }

        return [
            'widgets' => self::normalizeWidgets($dashboard['widgets'] ?? null, $defaults['widgets']),
            'table_columns' => self::normalizeTableColumns($dashboard['table_columns'] ?? null, $form, $defaults['table_columns']),
            'saved_views' => self::normalizeSavedViews($dashboard['saved_views'] ?? null, $defaults['saved_views']),
        ];
    }

    /**
     * @return list<array{key: string, label: string, kind: string, field_name?: string, visible: bool, order: int}>
     */
    public static function defaultTableColumns(?EApprovalForm $form = null): array
    {
        $columns = [
            ['key' => 'document_no', 'label' => 'Document', 'kind' => 'system', 'visible' => true, 'order' => 1],
            ['key' => 'status', 'label' => 'Status', 'kind' => 'system', 'visible' => true, 'order' => 2],
            ['key' => 'requestor', 'label' => 'Requestor', 'kind' => 'system', 'visible' => true, 'order' => 3],
            ['key' => 'current_step', 'label' => 'Step', 'kind' => 'system', 'visible' => true, 'order' => 4],
        ];

        if ($form === null) {
            return $columns;
        }

        $order = 5;
        foreach (self::exportableFields($form) as $field) {
            if ($order > 7) {
                break;
            }

            $name = trim((string) $field->name);
            if ($name === '') {
                continue;
            }

            $label = trim((string) ($field->label ?? '')) ?: $name;
            $columns[] = [
                'key' => 'field:'.$name,
                'label' => $label,
                'kind' => 'field',
                'field_name' => $name,
                'visible' => true,
                'order' => $order,
            ];
            $order++;
        }

        return $columns;
    }

    /**
     * @return list<array{key: string, label: string, kind: string, field_name?: string}>
     */
    public static function availableTableColumns(EApprovalForm $form): array
    {
        $columns = [
            ['key' => 'document_no', 'label' => 'Document', 'kind' => 'system'],
            ['key' => 'status', 'label' => 'Status', 'kind' => 'system'],
            ['key' => 'requestor', 'label' => 'Requestor', 'kind' => 'system'],
            ['key' => 'current_step', 'label' => 'Step', 'kind' => 'system'],
            ['key' => 'created_at', 'label' => 'Submitted', 'kind' => 'system'],
        ];

        foreach (self::exportableFields($form) as $field) {
            $name = trim((string) $field->name);
            if ($name === '') {
                continue;
            }

            $columns[] = [
                'key' => 'field:'.$name,
                'label' => trim((string) ($field->label ?? '')) ?: $name,
                'kind' => 'field',
                'field_name' => $name,
            ];
        }

        return $columns;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @return list<array{field_name: string}>
     */
    public static function visibleFieldColumns(array $columns): array
    {
        $fieldColumns = [];

        foreach ($columns as $column) {
            if (($column['visible'] ?? true) !== true) {
                continue;
            }
            if (($column['kind'] ?? '') !== 'field') {
                continue;
            }
            $fieldName = trim((string) ($column['field_name'] ?? ''));
            if ($fieldName === '') {
                continue;
            }
            $fieldColumns[] = ['field_name' => $fieldName];
        }

        return $fieldColumns;
    }

    /**
     * @param  mixed  $widgets
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array<string, mixed>>
     */
    private static function normalizeWidgets(mixed $widgets, array $defaults): array
    {
        if (! is_array($widgets) || $widgets === []) {
            return $defaults;
        }

        $normalized = [];
        foreach ($widgets as $index => $widget) {
            if (! is_array($widget)) {
                continue;
            }
            $type = trim((string) ($widget['type'] ?? ''));
            if (! in_array($type, self::WIDGET_TYPES, true)) {
                continue;
            }
            $normalized[] = [
                'id' => trim((string) ($widget['id'] ?? $type)) ?: $type,
                'type' => $type,
                'enabled' => ($widget['enabled'] ?? true) !== false,
                'order' => max(1, (int) ($widget['order'] ?? $index + 1)),
            ];
        }

        if ($normalized === []) {
            return $defaults;
        }

        usort($normalized, static fn (array $a, array $b): int => ($a['order'] <=> $b['order']));

        return $normalized;
    }

    /**
     * @param  mixed  $columns
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array<string, mixed>>
     */
    private static function normalizeTableColumns(mixed $columns, ?EApprovalForm $form, array $defaults): array
    {
        if (! is_array($columns) || $columns === []) {
            return $defaults;
        }

        $available = $form !== null
            ? collect(self::availableTableColumns($form))->keyBy('key')
            : collect();

        $normalized = [];
        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }

            $kind = ($column['kind'] ?? '') === 'field' ? 'field' : 'system';
            $key = trim((string) ($column['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            if ($kind === 'system' && ! in_array($key, self::SYSTEM_COLUMN_KEYS, true)) {
                continue;
            }

            $label = trim((string) ($column['label'] ?? ''));
            if ($form !== null && $available->has($key)) {
                $availableColumn = $available->get($key);
                if ($label === '') {
                    $label = (string) ($availableColumn['label'] ?? $key);
                }
            }

            $entry = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
                'kind' => $kind,
                'visible' => ($column['visible'] ?? true) !== false,
                'order' => max(1, (int) ($column['order'] ?? $index + 1)),
            ];

            if ($kind === 'field') {
                $fieldName = trim((string) ($column['field_name'] ?? ''));
                if ($fieldName === '' && str_starts_with($key, 'field:')) {
                    $fieldName = substr($key, 6);
                }
                if ($fieldName === '') {
                    continue;
                }
                $entry['field_name'] = $fieldName;
                $entry['key'] = 'field:'.$fieldName;
            }

            $normalized[] = $entry;
        }

        if ($normalized === []) {
            return $defaults;
        }

        usort($normalized, static fn (array $a, array $b): int => ($a['order'] <=> $b['order']));

        return $normalized;
    }

    /**
     * @param  mixed  $views
     * @param  list<array<string, mixed>>  $defaults
     * @return list<array<string, mixed>>
     */
    private static function normalizeSavedViews(mixed $views, array $defaults): array
    {
        if (! is_array($views) || $views === []) {
            return $defaults;
        }

        $normalized = [];
        foreach ($views as $index => $view) {
            if (! is_array($view)) {
                continue;
            }

            $id = trim((string) ($view['id'] ?? ''));
            $label = trim((string) ($view['label'] ?? ''));
            if ($id === '' || $label === '') {
                continue;
            }

            $entry = [
                'id' => $id,
                'label' => $label,
                'order' => max(1, (int) ($view['order'] ?? $index + 1)),
            ];

            $status = trim((string) ($view['status'] ?? ''));
            if ($status !== '' && $status !== 'all') {
                $entry['status'] = $status;
            }

            if (($view['mine'] ?? false) === true) {
                $entry['mine'] = true;
            }

            $periodDays = $view['period_days'] ?? null;
            if (is_numeric($periodDays) && (int) $periodDays > 0) {
                $entry['period_days'] = (int) $periodDays;
            }

            $normalized[] = $entry;
        }

        if ($normalized === []) {
            return $defaults;
        }

        usort($normalized, static fn (array $a, array $b): int => ($a['order'] <=> $b['order']));

        return $normalized;
    }

    /**
     * @return \Illuminate\Support\Collection<int, EApprovalFormField>
     */
    private static function exportableFields(EApprovalForm $form): \Illuminate\Support\Collection
    {
        return EApprovalFormField::query()
            ->where('form_id', $form->id)
            ->whereNotIn('type', self::SKIP_FIELD_TYPES)
            ->orderBy('step_order')
            ->orderBy('name')
            ->get();
    }
}
