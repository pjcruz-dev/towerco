<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

final class EApprovalFieldOptionsParser
{
    /**
     * @param  array<string, mixed>|null  $options
     * @return list<array{value: string, label: string, inputs?: list<array{key: string, type: string, suffix?: string, placeholder?: string, required?: bool}>}>
     */
    public static function selectChoices(?array $options): array
    {
        if ($options === null || $options === []) {
            return [];
        }

        $entries = array_is_list($options)
            ? $options
            : (is_array($options['choices'] ?? null) ? $options['choices'] : []);

        $choices = [];
        foreach ($entries as $entry) {
            $parsed = self::parseChoiceEntry($entry);
            if ($parsed !== null) {
                $choices[] = $parsed;
            }
        }

        return $choices;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return list<string>
     */
    public static function gridColumns(?array $options): array
    {
        if ($options === null || $options === []) {
            return [];
        }

        if (array_is_list($options)) {
            return array_values(array_filter(array_map(static function (mixed $col): string {
                if (is_string($col)) {
                    return trim($col);
                }

                if (is_array($col)) {
                    return trim((string) ($col['label'] ?? $col['name'] ?? ''));
                }

                return '';
            }, $options), static fn (string $col): bool => $col !== ''));
        }

        $columns = $options['columns'] ?? null;
        if (! is_array($columns)) {
            return [];
        }

        return array_values(array_filter(array_map(static function (mixed $col): string {
            if (is_string($col)) {
                return trim($col);
            }

            if (is_array($col)) {
                $label = trim((string) ($col['label'] ?? $col['name'] ?? ''));

                return $label;
            }

            return '';
        }, $columns), static fn (string $col): bool => $col !== ''));
    }

    public static function choiceLabel(?array $options, ?string $rawValue): ?string
    {
        if ($rawValue === null || $rawValue === '') {
            return $rawValue;
        }

        foreach (self::selectChoices($options) as $choice) {
            if ($choice['value'] === $rawValue) {
                return $choice['label'] !== '' ? $choice['label'] : $rawValue;
            }
        }

        return $rawValue;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return array{rows: list<array{value: string, label: string}>, columns: list<array{value: string, label: string}>}
     */
    public static function matrixAxes(?array $options): array
    {
        $rows = self::parseAxisList(is_array($options['rows'] ?? null) ? $options['rows'] : [], 'row');
        $columns = self::parseAxisList(is_array($options['columns'] ?? null) ? $options['columns'] : [], 'col');

        if ($rows === []) {
            $rows = [
                ['value' => 'a', 'label' => 'A. Item A'],
                ['value' => 'b', 'label' => 'B. Item B'],
            ];
        }

        if ($columns === []) {
            $columns = [
                ['value' => 'yes', 'label' => 'Yes'],
                ['value' => 'no', 'label' => 'No'],
            ];
        }

        return ['rows' => $rows, 'columns' => $columns];
    }

    /**
     * Checklist matrix: fixed rows with typed columns (text/number/date/select/…).
     *
     * @param  array<string, mixed>|null  $options
     * @return array{
     *     rows: list<array{value: string, label: string}>,
     *     columns: list<array{value: string, label: string, type: string, choices?: list<array{value: string, label: string}>, master_data_key?: string}>,
     *     row_select_label: string
     * }
     */
    public static function checklistMatrixAxes(?array $options): array
    {
        $rows = self::parseAxisList(is_array($options['rows'] ?? null) ? $options['rows'] : [], 'row');
        $columns = self::parseChecklistMatrixColumns(is_array($options['columns'] ?? null) ? $options['columns'] : []);
        $rowSelectLabel = trim((string) ($options['row_select_label'] ?? ''));

        if ($rows === []) {
            $rows = [
                ['value' => 'saq_site_survey', 'label' => 'SAQ-Site Survey'],
                ['value' => 'saq_permitting', 'label' => 'SAQ-Permitting'],
                ['value' => 'saq_soil_testing', 'label' => 'SAQ Soil Testing'],
                ['value' => 'cme_materials', 'label' => 'CME-Materials'],
                ['value' => 'cme_labor', 'label' => 'CME-Labor'],
                ['value' => 'logistics', 'label' => 'Logistics'],
                ['value' => 'various_department', 'label' => 'Various Department'],
                ['value' => 'finance_and_accounting', 'label' => 'Finance and Accounting'],
                ['value' => 'others', 'label' => 'Others'],
            ];
        }

        if ($columns === []) {
            $columns = [
                ['value' => 'project_site_no', 'label' => 'Project Site No', 'type' => 'text'],
                ['value' => 'ref_no', 'label' => 'Ref No', 'type' => 'text'],
                ['value' => 'or_no', 'label' => 'OR No.', 'type' => 'text'],
            ];
        }

        return [
            'rows' => $rows,
            'columns' => $columns,
            'row_select_label' => $rowSelectLabel !== '' ? $rowSelectLabel : 'Cost Application',
        ];
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<array{value: string, label: string, type: string, choices?: list<array{value: string, label: string}>, master_data_key?: string}>
     */
    private static function parseChecklistMatrixColumns(array $entries): array
    {
        $out = [];
        $seen = [];
        foreach ($entries as $index => $entry) {
            $parsed = self::parseChecklistMatrixColumnEntry($entry, (int) $index);
            if ($parsed === null || isset($seen[$parsed['value']])) {
                continue;
            }
            $seen[$parsed['value']] = true;
            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * @return array{value: string, label: string, type: string, choices?: list<array{value: string, label: string}>, master_data_key?: string}|null
     */
    private static function parseChecklistMatrixColumnEntry(mixed $entry, int $index): ?array
    {
        $axis = self::parseAxisEntry($entry, $index, 'col');
        if ($axis === null) {
            return null;
        }

        $type = 'text';
        $column = [
            'value' => $axis['value'],
            'label' => $axis['label'],
            'type' => $type,
        ];

        if (! is_array($entry)) {
            return $column;
        }

        $typeRaw = strtolower(trim((string) ($entry['type'] ?? 'text')));
        $allowed = ['text', 'number', 'currency', 'date', 'select'];
        $type = in_array($typeRaw, $allowed, true) ? $typeRaw : 'text';
        $column['type'] = $type;

        if ($type === 'select') {
            $masterKey = trim((string) ($entry['master_data_key'] ?? ''));
            if ($masterKey !== '') {
                $column['master_data_key'] = $masterKey;
            } else {
                $choices = self::parseAxisList(is_array($entry['choices'] ?? null) ? $entry['choices'] : [], 'opt');
                $column['choices'] = $choices !== [] ? $choices : [
                    ['value' => 'a', 'label' => 'Option A'],
                ];
            }
        }

        return $column;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return list<array{value: string, label: string, input: string}>
     */
    public static function sizeMatrixRows(?array $options): array
    {
        $rows = self::parseSizeMatrixRowList(is_array($options['rows'] ?? null) ? $options['rows'] : []);
        if ($rows !== []) {
            return $rows;
        }

        return [
            ['value' => 'roofdeck', 'label' => 'Roofdeck', 'input' => 'size'],
            ['value' => 'elevator_shaft', 'label' => 'Elevator Shaft', 'input' => 'size'],
            ['value' => 'water_tank', 'label' => 'Water Tank', 'input' => 'size'],
            ['value' => 'wall', 'label' => 'Wall', 'input' => 'size'],
            ['value' => 'other', 'label' => 'Other (specify)', 'input' => 'text'],
            ['value' => 'existing_utilities', 'label' => 'Existing Utilities', 'input' => 'text'],
        ];
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<array{value: string, label: string, input: string}>
     */
    private static function parseSizeMatrixRowList(array $entries): array
    {
        $out = [];
        $seen = [];
        foreach ($entries as $index => $entry) {
            $parsed = self::parseSizeMatrixRowEntry($entry, (int) $index);
            if ($parsed === null || isset($seen[$parsed['value']])) {
                continue;
            }
            $seen[$parsed['value']] = true;
            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * @return array{value: string, label: string, input: string}|null
     */
    private static function parseSizeMatrixRowEntry(mixed $entry, int $index): ?array
    {
        if (is_string($entry)) {
            $label = trim($entry);
            if ($label === '') {
                return null;
            }

            return ['value' => 'row_'.($index + 1), 'label' => $label, 'input' => 'size'];
        }

        if (! is_array($entry)) {
            return null;
        }

        $value = trim((string) ($entry['value'] ?? ''));
        $label = trim((string) ($entry['label'] ?? $value));
        if ($value === '' && $label === '') {
            return null;
        }

        $input = strtolower(trim((string) ($entry['input'] ?? 'size'))) === 'text' ? 'text' : 'size';

        return [
            'value' => $value !== '' ? $value : 'row_'.($index + 1),
            'label' => $label !== '' ? $label : $value,
            'input' => $input,
        ];
    }

    /**
     * @param  list<mixed>  $entries
     * @return list<array{value: string, label: string}>
     */
    private static function parseAxisList(array $entries, string $prefix): array
    {
        $out = [];
        $seen = [];
        foreach ($entries as $index => $entry) {
            $parsed = self::parseAxisEntry($entry, (int) $index, $prefix);
            if ($parsed === null || isset($seen[$parsed['value']])) {
                continue;
            }
            $seen[$parsed['value']] = true;
            $out[] = $parsed;
        }

        return $out;
    }

    /**
     * @return array{value: string, label: string}|null
     */
    private static function parseAxisEntry(mixed $entry, int $index, string $prefix): ?array
    {
        if (is_string($entry)) {
            $label = trim($entry);
            if ($label === '') {
                return null;
            }

            return ['value' => $prefix.'_'.($index + 1), 'label' => $label];
        }

        if (! is_array($entry)) {
            return null;
        }

        $value = trim((string) ($entry['value'] ?? ''));
        $label = trim((string) ($entry['label'] ?? $value));
        if ($value === '' && $label === '') {
            return null;
        }

        return [
            'value' => $value !== '' ? $value : $prefix.'_'.($index + 1),
            'label' => $label !== '' ? $label : $value,
        ];
    }

    /**
     * @return array{value: string, label: string, inputs?: list<array{key: string, type: string, suffix?: string, placeholder?: string, required?: bool}>}|null
     */
    private static function parseChoiceEntry(mixed $entry): ?array
    {
        if (is_array($entry) && isset($entry['value'])) {
            $value = trim((string) $entry['value']);
            if ($value === '') {
                return null;
            }
            $label = trim((string) ($entry['label'] ?? $value));

            $choice = [
                'value' => $value,
                'label' => $label !== '' ? $label : $value,
            ];

            $inputs = self::parseCompanionInputs($entry['inputs'] ?? null);
            if ($inputs !== []) {
                $choice['inputs'] = $inputs;
            }

            $help = trim((string) ($entry['help'] ?? ''));
            if ($help !== '') {
                $choice['help'] = $help;
            }

            return $choice;
        }

        if (! is_string($entry)) {
            return null;
        }

        $text = trim($entry);
        if ($text === '') {
            return null;
        }

        $pipe = strpos($text, '|');
        if ($pipe !== false) {
            $label = trim(substr($text, 0, $pipe));
            $value = trim(substr($text, $pipe + 1));

            return [
                'value' => $value !== '' ? $value : $label,
                'label' => $label !== '' ? $label : $value,
            ];
        }

        return ['value' => $text, 'label' => $text];
    }

    /**
     * @return list<array{key: string, type: string, suffix?: string, placeholder?: string, required?: bool}>
     */
    private static function parseCompanionInputs(mixed $raw): array
    {
        if (! is_array($raw) || $raw === []) {
            return [];
        }

        $inputs = [];
        $seen = [];
        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $type = strtolower(trim((string) ($item['type'] ?? 'text')));
            $input = [
                'key' => $key,
                'type' => match ($type) {
                    'number' => 'number',
                    'size' => 'size',
                    default => 'text',
                },
            ];
            $suffix = trim((string) ($item['suffix'] ?? ''));
            if ($suffix !== '') {
                $input['suffix'] = $suffix;
            }
            $placeholder = trim((string) ($item['placeholder'] ?? ''));
            if ($placeholder !== '') {
                $input['placeholder'] = $placeholder;
            }
            if (($item['required'] ?? false) === true) {
                $input['required'] = true;
            }
            $inputs[] = $input;
        }

        return $inputs;
    }
}
