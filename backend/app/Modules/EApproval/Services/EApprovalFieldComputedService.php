<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;

final class EApprovalFieldComputedService
{
    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function apply(EApprovalForm $form, array $values): array
    {
        $form->loadMissing('fields');
        $fields = $form->fields->all();
        $values = $this->applyGridRowAmountFormulas($fields, $values);

        for ($pass = 0; $pass < 12; $pass++) {
            $changed = false;

            foreach ($fields as $field) {
                if (! in_array((string) $field->type, ['currency', 'number'], true)) {
                    continue;
                }

                $config = $this->resolveConfig($field, $fields);
                if ($config === null) {
                    continue;
                }

                $computed = $this->computeValue($config, $fields, $values);
                if ($computed === null) {
                    continue;
                }

                $name = (string) $field->name;
                if (($values[$name] ?? null) !== $computed) {
                    $values[$name] = $computed;
                    $changed = true;
                }
            }

            if (! $changed) {
                break;
            }
        }

        return $values;
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function applyGridRowAmountFormulas(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            if ((string) $field->type !== 'grid') {
                continue;
            }

            $name = (string) $field->name;
            $raw = $values[$name] ?? '';
            $gridRaw = is_scalar($raw) ? trim((string) $raw) : '';
            if ($gridRaw === '') {
                continue;
            }

            $patched = $this->patchGridAmountColumn($field, $gridRaw);
            if ($patched !== $gridRaw) {
                $values[$name] = $patched;
            }
        }

        return $values;
    }

    private function patchGridAmountColumn(EApprovalFormField $gridField, string $gridRaw): string
    {
        $columns = $this->gridColumnDefs($gridField);
        $labels = array_map(static fn (array $column) => (string) $column['label'], $columns);
        $qtyIndex = $this->findColumnIndexByLabel($labels, ['qty', 'quantity']);
        $unitIndex = $this->findColumnIndexByLabel($labels, ['unit price', 'price', 'rate']);
        $discountIndex = $this->findColumnIndexByLabel($labels, ['discount']);
        $amountIndex = $this->findColumnIndexByLabel($labels, ['amount', 'line total']);

        if ($qtyIndex === null || $unitIndex === null || $amountIndex === null) {
            return $gridRaw;
        }

        $rows = $this->parseGridRows($gridRaw, count($columns));
        foreach ($rows as &$row) {
            $qty = $this->parseAmount($row[(string) $qtyIndex] ?? '');
            $unit = $this->parseAmount($row[(string) $unitIndex] ?? '');
            $discount = $discountIndex === null ? 0.0 : $this->parseAmount($row[(string) $discountIndex] ?? '');
            $row[(string) $amountIndex] = number_format(max(0, $qty * $unit - $discount), 2, '.', '');
        }
        unset($row);

        return json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
    }

    /**
     * @param  list<string>  $labels
     * @param  list<string>  $needles
     */
    private function findColumnIndexByLabel(array $labels, array $needles): ?int
    {
        foreach ($labels as $index => $label) {
            $normalized = strtolower(trim($label));
            foreach ($needles as $needle) {
                if ($normalized === strtolower($needle) || str_contains($normalized, strtolower($needle))) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     * @return array<string, mixed>|null
     */
    private function resolveConfig(EApprovalFormField $field, array $fields): ?array
    {
        $options = is_array($field->options) ? $field->options : [];
        $explicit = $options['computed_from'] ?? null;
        if (is_array($explicit) && isset($explicit['operation'])) {
            return $explicit;
        }

        return match ((string) $field->name) {
            'total_reimbursement' => $this->hasGridField($fields, 'expense_lines')
                ? [
                    'operation' => 'sum_grid_column',
                    'source_field' => 'expense_lines',
                    'column' => 'Amount',
                ]
                : null,
            'estimated_total' => $this->hasGridField($fields, 'line_items')
                ? [
                    'operation' => 'sum_grid_lines',
                    'source_field' => 'line_items',
                    'quantity_column' => 'Qty',
                    'amount_column' => 'Unit price',
                ]
                : null,
            'total_amount' => $this->hasGridField($fields, 'line_items')
                ? [
                    'operation' => 'sum_grid_lines',
                    'source_field' => 'line_items',
                    'quantity_column' => 'Qty',
                    'amount_column' => 'Unit price',
                ]
                : null,
            'vatable_amount' => $this->hasGridField($fields, 'line_items')
                ? [
                    'operation' => 'sum_grid_column',
                    'source_field' => 'line_items',
                    'column' => 'Amount',
                ]
                : null,
            'vat_amount' => $this->fieldExists($fields, 'vatable_amount')
                ? [
                    'operation' => 'percent_of',
                    'source_field' => 'vatable_amount',
                    'rate_field' => 'vat_rate',
                ]
                : null,
            'total_vat_inclusive' => $this->fieldsExist($fields, ['vatable_amount', 'vat_amount'])
                ? [
                    'operation' => 'add_fields',
                    'fields' => ['vatable_amount', 'vat_exempt_amount', 'zero_rated_amount', 'vat_amount'],
                ]
                : null,
            'grand_total' => $this->fieldsExist($fields, ['total_vat_inclusive', 'less_discount'])
                ? [
                    'operation' => 'subtract_fields',
                    'left_field' => 'total_vat_inclusive',
                    'right_field' => 'less_discount',
                ]
                : null,
            default => null,
        };
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     */
    private function hasGridField(array $fields, string $name): bool
    {
        foreach ($fields as $field) {
            if ((string) $field->name === $name && (string) $field->type === 'grid') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     */
    private function fieldExists(array $fields, string $name): bool
    {
        foreach ($fields as $field) {
            if ((string) $field->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     * @param  list<string>  $names
     */
    private function fieldsExist(array $fields, array $names): bool
    {
        foreach ($names as $name) {
            if (! $this->fieldExists($fields, $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     */
    private function computeValue(array $config, array $fields, array $values): ?string
    {
        $operation = (string) ($config['operation'] ?? '');
        $total = match ($operation) {
            'sum_grid_column', 'sum_grid_lines', 'sum_grid_lines_net' => $this->computeGridOperation($operation, $config, $fields, $values),
            'percent_of' => $this->computePercentOf($config, $values),
            'subtract_fields' => $this->readAmount($values, (string) ($config['left_field'] ?? ''))
                - $this->readAmount($values, (string) ($config['right_field'] ?? '')),
            'add_fields' => $this->computeAddFields($config, $values),
            default => null,
        };

        if ($total === null) {
            return null;
        }

        return number_format((float) $total, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     */
    private function computeGridOperation(string $operation, array $config, array $fields, array $values): ?float
    {
        $sourceName = (string) ($config['source_field'] ?? '');
        $gridField = null;
        foreach ($fields as $field) {
            if ((string) $field->name === $sourceName && (string) $field->type === 'grid') {
                $gridField = $field;
                break;
            }
        }

        if ($gridField === null) {
            return null;
        }

        $raw = $values[$sourceName] ?? '';
        $gridRaw = is_scalar($raw) ? trim((string) $raw) : '';
        if ($gridRaw === '') {
            return 0.0;
        }

        return match ($operation) {
            'sum_grid_lines' => $this->sumGridLines($gridField, $gridRaw, $config),
            'sum_grid_lines_net' => $this->sumGridLinesNet($gridField, $gridRaw, $config),
            default => $this->sumGridColumn($gridField, $gridRaw, $config),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function computePercentOf(array $config, array $values): float
    {
        $base = $this->readAmount($values, (string) ($config['source_field'] ?? ''));
        $rateField = isset($config['rate_field']) ? (string) $config['rate_field'] : '';
        $rate = $rateField !== '' ? $this->readAmount($values, $rateField) : (float) ($config['rate'] ?? 0);

        return ($base * $rate) / 100;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $values
     */
    private function computeAddFields(array $config, array $values): float
    {
        $fieldNames = is_array($config['fields'] ?? null) ? $config['fields'] : [];
        $total = 0.0;
        foreach ($fieldNames as $fieldName) {
            $total += $this->readAmount($values, (string) $fieldName);
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function readAmount(array $values, string $fieldName): float
    {
        if ($fieldName === '') {
            return 0.0;
        }

        $raw = $values[$fieldName] ?? '';

        return $this->parseAmount(is_scalar($raw) ? (string) $raw : '');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sumGridColumn(EApprovalFormField $gridField, string $gridRaw, array $config): float
    {
        $columns = $this->gridColumnDefs($gridField);
        $columnIndex = $this->resolveColumnIndex(
            $columns,
            isset($config['column']) ? (string) $config['column'] : null,
            isset($config['column_index']) ? (int) $config['column_index'] : null,
        );

        if ($columnIndex === null) {
            foreach ($columns as $index => $column) {
                if (($column['type'] ?? 'text') === 'currency') {
                    $columnIndex = $index;
                    break;
                }
            }
        }

        if ($columnIndex === null) {
            return 0.0;
        }

        $rows = $this->parseGridRows($gridRaw, count($columns));
        $total = 0.0;
        foreach ($rows as $row) {
            $total += $this->parseAmount($row[(string) $columnIndex] ?? '');
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sumGridLines(EApprovalFormField $gridField, string $gridRaw, array $config): ?float
    {
        $columns = $this->gridColumnDefs($gridField);
        $qtyIndex = $this->resolveColumnIndex(
            $columns,
            isset($config['quantity_column']) ? (string) $config['quantity_column'] : null,
            isset($config['quantity_column_index']) ? (int) $config['quantity_column_index'] : null,
        );
        $amountIndex = $this->resolveColumnIndex(
            $columns,
            isset($config['amount_column']) ? (string) $config['amount_column'] : null,
            isset($config['amount_column_index']) ? (int) $config['amount_column_index'] : null,
        );

        if ($qtyIndex === null || $amountIndex === null) {
            return null;
        }

        $rows = $this->parseGridRows($gridRaw, count($columns));
        $total = 0.0;
        foreach ($rows as $row) {
            $qty = $this->parseAmount($row[(string) $qtyIndex] ?? '');
            $amount = $this->parseAmount($row[(string) $amountIndex] ?? '');
            $total += $qty * $amount;
        }

        return $total;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function sumGridLinesNet(EApprovalFormField $gridField, string $gridRaw, array $config): float
    {
        $columns = $this->gridColumnDefs($gridField);
        $qtyIndex = $this->resolveColumnIndex($columns, (string) ($config['quantity_column'] ?? 'Qty'), null)
            ?? $this->resolveColumnIndex($columns, 'Qty', null);
        $unitIndex = $this->resolveColumnIndex($columns, (string) ($config['amount_column'] ?? 'Unit price'), null)
            ?? $this->resolveColumnIndex($columns, 'Unit price', null);
        $discountIndex = $this->resolveColumnIndex($columns, (string) ($config['discount_column'] ?? 'Discount'), null)
            ?? $this->resolveColumnIndex($columns, 'Discount', null);

        if ($qtyIndex === null || $unitIndex === null) {
            return 0.0;
        }

        $rows = $this->parseGridRows($gridRaw, count($columns));
        $total = 0.0;
        foreach ($rows as $row) {
            $qty = $this->parseAmount($row[(string) $qtyIndex] ?? '');
            $unit = $this->parseAmount($row[(string) $unitIndex] ?? '');
            $discount = $discountIndex === null ? 0.0 : $this->parseAmount($row[(string) $discountIndex] ?? '');
            $total += max(0, $qty * $unit - $discount);
        }

        return $total;
    }

    /**
     * @return list<array{label: string, type: string}>
     */
    private function gridColumnDefs(EApprovalFormField $gridField): array
    {
        $options = is_array($gridField->options) ? $gridField->options : [];
        $labels = EApprovalFieldOptionsParser::gridColumns($options);
        $rawColumns = array_is_list($options) ? $options : (is_array($options['columns'] ?? null) ? $options['columns'] : []);

        $defs = [];
        foreach ($labels as $index => $label) {
            $raw = $rawColumns[$index] ?? null;
            $type = 'text';
            if (is_array($raw)) {
                $type = strtolower((string) ($raw['type'] ?? 'text'));
            }

            $defs[] = ['label' => $label, 'type' => $type];
        }

        return $defs;
    }

    /**
     * @param  list<array{label: string, type: string}>  $columns
     */
    private function resolveColumnIndex(array $columns, ?string $label, ?int $index): ?int
    {
        if ($index !== null && $index >= 0 && $index < count($columns)) {
            return $index;
        }

        if ($label === null || trim($label) === '') {
            return null;
        }

        $needle = strtolower(trim($label));
        foreach ($columns as $i => $column) {
            if (strtolower(trim($column['label'])) === $needle) {
                return $i;
            }
        }

        return null;
    }

    /**
     * @return list<array<string, string>>
     */
    private function parseGridRows(string $gridRaw, int $columnCount): array
    {
        try {
            $decoded = json_decode($gridRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : (is_array($decoded) ? $decoded : []);
        $normalized = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $next = [];
            for ($i = 0; $i < $columnCount; $i++) {
                $key = (string) $i;
                $value = $row[$key] ?? $row[$i] ?? '';
                $next[$key] = is_scalar($value) ? trim((string) $value) : '';
            }
            $normalized[] = $next;
        }

        return $normalized;
    }

    private function parseAmount(string $raw): float
    {
        $trimmed = trim(str_replace(',', '', $raw));
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            return 0.0;
        }

        return (float) $trimmed;
    }
}
