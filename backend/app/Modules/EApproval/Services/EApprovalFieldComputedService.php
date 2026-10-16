<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalFormField;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use App\Modules\EApproval\Support\EApprovalPerDiemDays;

final class EApprovalFieldComputedService
{
    public function __construct(
        private readonly ?EApprovalFinanceProcurementPolicyService $policy = null,
    ) {}

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function apply(EApprovalForm $form, array $values): array
    {
        $form->loadMissing('fields');
        $fields = $form->fields->all();
        $values = $this->applyGridRowAmountFormulas($fields, $values);
        $values = $this->applyExpenseVat($fields, $values);

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

        return $this->applySettlementSummary($fields, $values);
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

    /**
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function applyExpenseVat(array $fields, array $values): array
    {
        foreach ($fields as $field) {
            if ((string) $field->name !== 'expense_lines' || (string) $field->type !== 'grid') {
                continue;
            }

            $name = (string) $field->name;
            $raw = $values[$name] ?? '';
            $gridRaw = is_scalar($raw) ? trim((string) $raw) : '';
            if ($gridRaw === '') {
                continue;
            }

            $columns = $this->gridColumnDefs($field);
            $keepsTypedAmount = $this->gridKeepsTypedAmount($columns);
            $amountIndex = $this->resolveColumnIndex($columns, 'Amount', null);
            $vatIndex = $this->resolveColumnIndex($columns, 'VAT', null);
            $treatmentIndex = $this->resolveColumnIndex($columns, 'VAT treatment', null);
            $qtyIndex = $this->resolveColumnIndex($columns, 'Quantity', null);
            $rateIndex = $this->resolveColumnIndex($columns, 'Rate', null);
            $lineIndex = $amountIndex ?? $rateIndex;
            if ($lineIndex === null || $vatIndex === null) {
                continue;
            }

            $rate = $this->policy?->vatRatePercent() ?? 12;
            $inclusive = $this->policy?->amountIncludesVat() ?? true;
            $rows = $this->parseGridRows($gridRaw, count($columns));
            $typeIndex = $this->resolveColumnIndex($columns, 'Expense type', null);
            $dateIndex = $this->resolveDateColumnIndex($columns);
            $nonVatable = 0.0;
            $vatableNet = 0.0;

            foreach ($rows as &$row) {
                $perDiem = $this->rowIsPerDiem($row, $typeIndex);
                $lodging = $this->rowIsLodging($row, $typeIndex);
                $extend = $perDiem || $lodging;
                $days = $perDiem
                    ? EApprovalPerDiemDays::forRow($row, $dateIndex)
                    : ($lodging ? EApprovalPerDiemDays::lodgingNightsForRow($row, $dateIndex) : null);
                if (! $keepsTypedAmount && $amountIndex !== null && $qtyIndex !== null && $rateIndex !== null && ! $extend) {
                    $qtyRaw = trim((string) ($row[(string) $qtyIndex] ?? ''));
                    $rateRaw = trim((string) ($row[(string) $rateIndex] ?? ''));
                    if ($qtyRaw !== '' || $rateRaw !== '') {
                        $line = max(0, $this->parseAmount($qtyRaw) * $this->parseAmount($rateRaw));
                        $row[(string) $amountIndex] = number_format($line, 2, '.', '');
                    }
                }
                if (! $keepsTypedAmount && $extend && $amountIndex !== null && $rateIndex !== null) {
                    $daily = $this->parseAmount($row[(string) $rateIndex] ?? '');
                    $row[(string) $amountIndex] = number_format(EApprovalPerDiemDays::lineTotal($daily, $days), 2, '.', '');
                }
                $amount = $this->expenseLineMoney(
                    $row,
                    $amountIndex,
                    $keepsTypedAmount ? null : $rateIndex,
                    $keepsTypedAmount ? false : $extend,
                    $days,
                );
                $treatment = '';
                if ($treatmentIndex !== null) {
                    $treatment = strtolower(trim($row[(string) $treatmentIndex] ?? ''));
                }
                $vatable = ! $perDiem && ! in_array($treatment, ['non_vatable', 'non-vatable'], true);
                if (! $vatable || $rate <= 0) {
                    $vat = 0.0;
                    $nonVatable += $amount;
                } elseif ($inclusive) {
                    $vat = round($amount * $rate / (100 + $rate), 2);
                    $vatableNet += round($amount - $vat, 2);
                } else {
                    $vat = round($amount * $rate / 100, 2);
                    $vatableNet += $amount;
                }
                $row[(string) $vatIndex] = number_format($vat, 2, '.', '');
            }
            unset($row);

            $values[$name] = json_encode(['rows' => $rows], JSON_THROW_ON_ERROR);
            if ($this->fieldExists($fields, 'non_vatable_total')) {
                $values['non_vatable_total'] = number_format($nonVatable, 2, '.', '');
            }
            if ($this->fieldExists($fields, 'vatable_net')) {
                $values['vatable_net'] = number_format($vatableNet, 2, '.', '');
            }
        }

        return $values;
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function applyExclusiveGross(array $fields, array $values): array
    {
        if ($this->policy === null || $this->policy->amountIncludesVat()) {
            return $values;
        }

        $vat = $this->readAmount($values, 'vat_total');
        foreach (['requested_amount', 'total_reimbursement'] as $name) {
            if (! $this->fieldExists($fields, $name)) {
                continue;
            }
            $values[$name] = number_format($this->readAmount($values, $name) + $vat, 2, '.', '');
        }

        return $values;
    }

    /**
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function applySettlementSummary(array $fields, array $values): array
    {
        $values = $this->applyExclusiveGross($fields, $values);
        if (! $this->fieldExists($fields, 'settlement_summary')) {
            return $values;
        }
        if (! $this->fieldsExist($fields, ['total_reimbursement', 'cash_advance_amount'])) {
            return $values;
        }

        $delta = round(
            $this->readAmount($values, 'total_reimbursement') - $this->readAmount($values, 'cash_advance_amount'),
            2,
        );
        if (abs($delta) < 0.005) {
            $values['settlement_summary'] = 'Settled. No cash to move.';
        } elseif ($delta > 0) {
            $values['settlement_summary'] = 'Amount to pay the employee: '.number_format($delta, 2, '.', '');
        } else {
            $values['settlement_summary'] = 'Amount to surrender: '.number_format(abs($delta), 2, '.', '');
        }

        return $values;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function expenseLineMoney(array $row, ?int $amountIndex, ?int $rateIndex, bool $perDiem, ?int $days): float
    {
        $read = function (?int $index) use ($row): float {
            if ($index === null) {
                return 0.0;
            }

            return max(0, $this->parseAmount($row[(string) $index] ?? ''));
        };

        if (! $perDiem) {
            return round($read($amountIndex ?? $rateIndex), 2);
        }

        $daily = $rateIndex !== null ? $read($rateIndex) : $read($amountIndex);

        return EApprovalPerDiemDays::lineTotal($daily, $days);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowIsPerDiem(array $row, ?int $typeIndex): bool
    {
        if ($typeIndex === null) {
            return false;
        }

        $type = strtolower(trim((string) ($row[(string) $typeIndex] ?? '')));

        return $type === 'per_diem' || $type === 'per diem';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function rowIsLodging(array $row, ?int $typeIndex): bool
    {
        if ($typeIndex === null) {
            return false;
        }

        $type = strtolower(trim((string) ($row[(string) $typeIndex] ?? '')));

        return in_array($type, [
            'lodging',
            'hotel_accommodation',
            'hotel & accommodation',
            'hotel & accomodation',
            'hotel and accommodation',
        ], true);
    }

    private function patchGridAmountColumn(EApprovalFormField $gridField, string $gridRaw): string
    {
        $columns = $this->gridColumnDefs($gridField);
        $labels = array_map(static fn (array $column) => (string) $column['label'], $columns);
        $qtyIndex = $this->findColumnIndexByLabel($labels, ['qty', 'quantity']);
        $unitIndex = $this->findColumnIndexByLabel($labels, ['unit price', 'price', 'rate']);
        $discountIndex = $this->findColumnIndexByLabel($labels, ['discount']);
        $amountIndex = $this->findColumnIndexByLabel($labels, ['amount', 'line total']);

        if ($this->gridKeepsTypedAmount($columns) || $qtyIndex === null || $unitIndex === null || $amountIndex === null) {
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
            'requested_amount' => $this->hasGridField($fields, 'expense_lines')
                ? [
                    'operation' => 'sum_grid_column',
                    'source_field' => 'expense_lines',
                    'column' => 'Amount',
                ]
                : null,
            'vat_total' => $this->hasGridField($fields, 'expense_lines')
                ? [
                    'operation' => 'sum_grid_column',
                    'source_field' => 'expense_lines',
                    'column' => 'VAT',
                ]
                : null,
            'settlement_amount' => $this->fieldsExist($fields, ['total_reimbursement', 'cash_advance_amount'])
                ? [
                    'operation' => 'subtract_fields',
                    'left_field' => 'total_reimbursement',
                    'right_field' => 'cash_advance_amount',
                ]
                : null,
            'total_reimbursement' => $this->hasGridField($fields, 'expense_lines')
                ? [
                    'operation' => 'sum_grid_column',
                    'source_field' => 'expense_lines',
                    'column' => $this->expenseLinesSumColumn($fields),
                ]
                : null,
            'cash_overage_shortage' => $this->fieldsExist($fields, ['cash_advance_amount', 'total_reimbursement'])
                ? [
                    'operation' => 'subtract_fields',
                    'left_field' => 'cash_advance_amount',
                    'right_field' => 'total_reimbursement',
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
     * Prefer "Total" (liquidation expense sheet); fall back to legacy "Amount".
     *
     * @param  list<EApprovalFormField>  $fields
     */
    private function expenseLinesSumColumn(array $fields): string
    {
        foreach ($fields as $field) {
            if ((string) $field->name !== 'expense_lines' || (string) $field->type !== 'grid') {
                continue;
            }
            $options = is_array($field->options) ? $field->options : [];
            $columns = is_array($options['columns'] ?? null) ? $options['columns'] : [];
            $labels = [];
            foreach ($columns as $column) {
                if (is_array($column)) {
                    $labels[] = strtolower(trim((string) ($column['label'] ?? '')));
                } elseif (is_string($column)) {
                    $labels[] = strtolower(trim($column));
                }
            }
            if (in_array('total', $labels, true)) {
                return 'Total';
            }
            if (in_array('amount', $labels, true)) {
                return 'Amount';
            }
            if (in_array('rate', $labels, true)) {
                return 'Rate';
            }
        }

        return 'Amount';
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
            default => $this->sumGridColumn($gridField, $gridRaw, $config, $fields, $values),
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
     * @param  list<EApprovalFormField>  $fields
     * @param  array<string, mixed>  $values
     */
    private function sumGridColumn(EApprovalFormField $gridField, string $gridRaw, array $config, array $fields, array $values): float
    {
        $columns = $this->gridColumnDefs($gridField);
        $columnIndex = $this->resolveColumnIndex(
            $columns,
            isset($config['column']) ? (string) $config['column'] : null,
            isset($config['column_index']) ? (int) $config['column_index'] : null,
        );

        if ($columnIndex === null) {
            $columnIndex = $this->resolveColumnIndex($columns, 'Total', null)
                ?? $this->resolveColumnIndex($columns, 'Amount', null);
        }

        if ($columnIndex === null) {
            // Prefer last currency column (row Total) over first category column.
            for ($index = count($columns) - 1; $index >= 0; $index--) {
                if (($columns[$index]['type'] ?? 'text') === 'currency') {
                    $columnIndex = $index;
                    break;
                }
            }
        }

        if ($columnIndex === null) {
            return 0.0;
        }

        $rows = $this->parseGridRows($gridRaw, count($columns));
        $extendPerDiem = (string) $gridField->name === 'expense_lines'
            && ! $this->gridKeepsTypedAmount($columns)
            && strcasecmp(trim((string) ($columns[$columnIndex]['label'] ?? '')), 'Amount') === 0;
        $typeIndex = $extendPerDiem ? $this->resolveColumnIndex($columns, 'Expense type', null) : null;
        $rateIndex = $extendPerDiem ? $this->resolveColumnIndex($columns, 'Rate', null) : null;
        $dateIndex = $extendPerDiem ? $this->resolveDateColumnIndex($columns) : null;
        $total = 0.0;
        foreach ($rows as $row) {
            if ($extendPerDiem) {
                $perDiem = $this->rowIsPerDiem($row, $typeIndex);
                $lodging = $this->rowIsLodging($row, $typeIndex);
                $days = $perDiem
                    ? EApprovalPerDiemDays::forRow($row, $dateIndex)
                    : ($lodging ? EApprovalPerDiemDays::lodgingNightsForRow($row, $dateIndex) : null);
                $total += $this->expenseLineMoney($row, $columnIndex, $rateIndex, $perDiem || $lodging, $days);

                continue;
            }
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
    private function gridKeepsTypedAmount(array $columns): bool
    {
        $labels = [];
        foreach ($columns as $column) {
            $label = strtolower(trim((string) ($column['label'] ?? '')));
            if ($label === 'forecast amount') {
                return true;
            }
            $labels[] = $label;
        }

        return in_array('or no', $labels, true)
            && in_array('expense type', $labels, true)
            && in_array('amount', $labels, true);
    }

    /**
     * @param  list<array{label: string, type: string}>  $columns
     */
    private function resolveDateColumnIndex(array $columns): ?int
    {
        foreach ($columns as $index => $column) {
            if (($column['type'] ?? '') === 'date_range') {
                return $index;
            }
            $label = strtolower(trim((string) ($column['label'] ?? '')));
            if ($label === 'date' || str_starts_with($label, 'from date')) {
                return $index;
            }
        }

        return null;
    }

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
