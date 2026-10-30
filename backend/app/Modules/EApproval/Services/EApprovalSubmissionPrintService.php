<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;
use App\Modules\EApproval\Support\EApprovalSiteStop;
use App\Modules\EApproval\Support\EApprovalWorkflowStepDefinitionSupport;

final class EApprovalSubmissionPrintService
{
    private const DEDICATED_PRINT_TEMPLATE_KINDS = [];

    public function __construct(
        private readonly EApprovalPdfLayoutService $pdfLayout,
        private readonly EApprovalSettingsService $settings,
        private readonly EApprovalFormValueDisplayService $valueDisplay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildPrintModel(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form', 'requestor', 'values.field', 'approvals.approver', 'approvals.step', 'attachments']);

        $layoutPayload = $this->pdfLayout->show((string) $submission->form_id);
        $layoutRows = is_array($layoutPayload['layout'] ?? null) ? $layoutPayload['layout'] : [];
        $template = is_array($layoutPayload['template'] ?? null) ? $layoutPayload['template'] : [];
        $layoutPersisted = (bool) ($layoutPayload['layout_persisted'] ?? false);

        $usersById = $this->valueDisplay->approverUsersById($submission->values);

        $valuesByKey = [];
        foreach ($submission->values as $row) {
            $key = $row->field?->name ?? (string) $row->field_id;
            $type = $row->field?->type;
            $raw = $row->value;
            $options = $row->field?->options;
            $valuesByKey[$key] = [
                'label' => $row->field?->label ?? $key,
                'value' => $this->valueDisplay->resolveDisplayValue(
                    $type,
                    $raw,
                    $usersById,
                    is_array($options) ? $options : null,
                ),
                'field_type' => is_string($type) ? $type : null,
            ];
        }

        $formMetadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        $printTemplateKind = (string) ($template['layout_kind'] ?? $formMetadata['print_template_kind'] ?? '');

        $fields = $this->resolvePrintFields($valuesByKey, $layoutRows, $layoutPersisted, $printTemplateKind);

        $template['subsidiary_logos'] = $this->pdfLayout->presentSubsidiaryLogoUrls(
            (string) $submission->form_id,
            $template,
        );
        if (! isset($template['subsidiary_logo_field']) || trim((string) $template['subsidiary_logo_field']) === '') {
            $template['subsidiary_logo_field'] = 'subsidiary';
        }

        return [
            'document_no' => $submission->document_no,
            'form_name' => $submission->form?->name,
            'form_family' => isset($formMetadata['form_family']) ? (string) $formMetadata['form_family'] : null,
            'status' => $submission->status,
            'requestor' => $submission->requestor?->name,
            'requestor_signature' => $submission->requestor_id
              ? $this->settings->getUserSignature((string) $submission->requestor_id)
              : null,
            'created_at' => $submission->created_at?->format('Y-m-d H:i'),
            'brand_logo_url' => $submission->form !== null
                ? app(EApprovalFileStorageService::class)->presentFormLogoUrl($submission->form)
                    ?? $submission->form->brand_logo_url
                : null,
            'subsidiary_logos' => $template['subsidiary_logos'],
            'print_template_kind' => $printTemplateKind !== '' ? $printTemplateKind : null,
            'fields' => $fields,
            'grids' => $this->buildGridPrintModels($submission),
            'approvals' => $submission->approvals->map(function ($a) use ($submission) {
                $condition = is_array($a->step?->condition) ? $a->step->condition : [];
                $labels = EApprovalWorkflowStepDefinitionSupport::decisionLabels(
                    $condition === [] ? [] : ['condition' => $condition],
                );
                $stepOrder = (int) ($a->step?->step_order ?? 0);

                return [
                    'step' => $a->step?->step_order,
                    'approver' => $a->approver?->name,
                    'status' => $a->status,
                    'remarks' => $a->remarks,
                    'signature' => $a->signature,
                    'acted_at' => $a->acted_at?->format('Y-m-d H:i'),
                    'step_label' => $a->step?->label,
                    'finance_action' => trim((string) ($condition['finance_action'] ?? '')) ?: null,
                    'decision_label' => $labels['approve'],
                    'proof_status' => $this->paymentProofStatus($submission, $stepOrder),
                ];
            })->values()->all(),
            'attachments' => $submission->attachments->reject(static fn ($a) => $a->isSuperseded())->map(static fn ($a) => [
                'id' => (string) $a->id,
                'file_name' => $a->file_name,
                'field_name' => $a->field_name,
                'metadata' => is_array($a->metadata) ? $a->metadata : null,
            ])->values()->all(),
            'template' => $template,
            'show_approval_trail' => count($submission->approvals) > 0,
        ];
    }

    /** Latest proof-of-payment status recorded on this step. Released and Hold are the choices. */
    private function paymentProofStatus(EApprovalSubmission $submission, int $stepOrder): ?string
    {
        if ($stepOrder < 1) {
            return null;
        }
        $finance = is_array($submission->finance_json) ? $submission->finance_json : [];
        $entries = is_array($finance['payment_proofs'] ?? null) ? $finance['payment_proofs'] : [];
        $status = null;
        foreach ($entries as $entry) {
            if (! is_array($entry) || (int) ($entry['step_order'] ?? 0) !== $stepOrder) {
                continue;
            }
            $next = strtolower(trim((string) ($entry['status'] ?? '')));
            if ($next === 'released' || $next === 'hold') {
                $status = $next;
            }
        }

        return $status;
    }

    /**
     * @param  array<string, array{label: string, value: string|null, field_type?: string|null}>  $valuesByKey
     * @param  list<mixed>  $layoutRows
     * @return list<array{key: string, label: string, value: string|null, field_type: string|null}>
     */
    private function resolvePrintFields(array $valuesByKey, array $layoutRows, bool $layoutPersisted, string $printTemplateKind): array
    {
        unset($layoutRows, $layoutPersisted);

        if ($this->usesDedicatedPrintTemplate($printTemplateKind)) {
            return $this->allPrintFields($valuesByKey);
        }

        // Document design + generic print always receive the full field set.
        // Field placement is controlled by template_html tokens, not visibility checkboxes.
        return $this->allPrintFields($valuesByKey);
    }

    /**
     * @param  array<string, array{label: string, value: string|null, field_type?: string|null}>  $valuesByKey
     * @return list<array{key: string, label: string, value: string|null, field_type: string|null}>
     */
    private function allPrintFields(array $valuesByKey): array
    {
        return array_map(
            static fn (array $row, string $key) => [
                'key' => $key,
                'label' => $row['label'],
                'value' => $row['value'],
                'field_type' => isset($row['field_type']) && is_string($row['field_type'])
                    ? $row['field_type']
                    : null,
            ],
            $valuesByKey,
            array_keys($valuesByKey),
        );
    }

    private function usesDedicatedPrintTemplate(string $printTemplateKind): bool
    {
        return in_array($printTemplateKind, self::DEDICATED_PRINT_TEMPLATE_KINDS, true);
    }

    private function buildGridPrintModels(EApprovalSubmission $submission): array
    {
        $submission->loadMissing(['form.fields', 'values.field']);

        $grids = [];
        $seen = [];

        foreach ($submission->values as $row) {
            if ((string) ($row->field?->type ?? '') !== 'grid') {
                continue;
            }

            $key = (string) ($row->field?->name ?? $row->field_id);
            $options = is_array($row->field?->options) ? $row->field->options : [];
            $layout = $this->gridPrintLayout($options);
            if ($layout['columns'] === [] && $layout['hidden'] === []) {
                continue;
            }

            $raw = is_scalar($row->value) ? trim((string) $row->value) : '';
            $parsedRows = $this->keepPrintColumns(
                $this->parseGridRowsForPrint($raw, $layout['all']),
                $layout['indexes'],
            );

            // Include empty grids so dynamic print body can still show column headers.
            $grids[] = [
                'key' => $key,
                'label' => (string) ($row->field?->label ?? $key),
                'columns' => $layout['columns'],
                'hidden_columns' => $layout['hidden'],
                'total_columns' => $layout['totals'],
                'rows' => $parsedRows,
            ];
            $seen[$key] = true;
        }

        // Always print form-defined grids (live columns from builder), even without a value row yet.
        foreach ($submission->form?->fields ?? [] as $field) {
            if ((string) ($field->type ?? '') !== 'grid') {
                continue;
            }
            $key = (string) ($field->name ?? '');
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $options = is_array($field->options) ? $field->options : [];
            $layout = $this->gridPrintLayout($options);
            if ($layout['columns'] === [] && $layout['hidden'] === []) {
                continue;
            }
            $grids[] = [
                'key' => $key,
                'label' => (string) ($field->label ?? $key),
                'columns' => $layout['columns'],
                'hidden_columns' => $layout['hidden'],
                'total_columns' => $layout['totals'],
                'rows' => [],
            ];
            $seen[$key] = true;
        }

        return $grids;
    }

    /**
     * @param  array<string, mixed>|null  $options
     * @return array{all: list<string>, columns: list<string>, hidden: list<string>, totals: list<string>, indexes: list<int>}
     */
    private function gridPrintLayout(?array $options): array
    {
        $all = EApprovalFieldOptionsParser::gridColumns($options);
        $raw = is_array($options) && is_array($options['columns'] ?? null) ? $options['columns'] : [];
        $hidden = [];
        $totals = [];
        foreach ($raw as $column) {
            if (! is_array($column)) {
                continue;
            }
            $label = trim((string) ($column['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            if (! empty($column['hidden'])) {
                $hidden[] = $label;

                continue;
            }
            if (! empty($column['show_total'])) {
                $totals[] = $label;
            }
        }
        $columns = [];
        $indexes = [];
        foreach ($all as $index => $label) {
            if (in_array($label, $hidden, true)) {
                continue;
            }
            $columns[] = $label;
            $indexes[] = (int) $index;
        }

        return [
            'all' => $all,
            'columns' => $columns,
            'hidden' => $hidden,
            'totals' => $totals,
            'indexes' => $indexes,
        ];
    }

    /**
     * @param  list<list<string>>  $rows
     * @param  list<int>  $indexes
     * @return list<list<string>>
     */
    private function keepPrintColumns(array $rows, array $indexes): array
    {
        $kept = [];
        foreach ($rows as $row) {
            $cells = [];
            foreach ($indexes as $index) {
                $cells[] = $row[$index] ?? '';
            }
            $kept[] = $cells;
        }

        return $kept;
    }

    /**
     * @param  list<string>  $columns
     * @return list<list<string>>
     */
    private function parseGridRowsForPrint(string $gridRaw, array $columns): array
    {
        if ($gridRaw === '' || $columns === []) {
            return [];
        }

        $labeledRows = $this->labeledGridRows($gridRaw, $columns);
        $normalized = [];

        foreach ($labeledRows as $row) {
            $cells = [];
            $hasContent = false;

            foreach ($columns as $index => $label) {
                $value = $this->resolveLabeledGridCell($row, $label, $index);

                if ($value === '' && $this->isAmountColumn($label)) {
                    $value = $this->computeGridLineAmount($row, $columns);
                }

                if ($value !== '') {
                    $hasContent = true;
                }

                $cells[] = $value;
            }

            $cells = $this->fillVatSplitCells($columns, $cells);
            if ($hasContent) {
                $normalized[] = $cells;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $cells
     * @return list<string>
     */
    private function fillVatSplitCells(array $columns, array $cells): array
    {
        $indexOf = static function (array $columns, callable $match): ?int {
            foreach ($columns as $index => $label) {
                if ($match(strtolower(trim((string) $label)))) {
                    return (int) $index;
                }
            }

            return null;
        };
        $nonIndex = $indexOf($columns, static fn (string $label): bool => $label === 'non-vatable' || $label === 'nonvatable');
        $netIndex = $indexOf($columns, static fn (string $label): bool => str_contains($label, 'net of vat'));
        if ($nonIndex === null && $netIndex === null) {
            return $cells;
        }
        $amountIndex = $indexOf($columns, static fn (string $label): bool => $label === 'amount');
        $treatmentIndex = $indexOf($columns, static fn (string $label): bool => $label === 'vat treatment');
        $vatIndex = $indexOf($columns, static fn (string $label): bool => $label === 'vat');
        $amount = $amountIndex !== null ? (float) str_replace([',', '₱', ' '], '', (string) ($cells[$amountIndex] ?? '0')) : 0.0;
        $treatment = $treatmentIndex !== null ? strtolower((string) ($cells[$treatmentIndex] ?? '')) : '';
        $nonVatable = str_contains($treatment, 'non');
        $vat = $vatIndex !== null ? (float) str_replace([',', '₱', ' '], '', (string) ($cells[$vatIndex] ?? '0')) : 0.0;
        $net = $nonVatable ? 0.0 : max(0, round($amount - $vat, 2));
        if ($nonIndex !== null) {
            $cells[$nonIndex] = number_format($nonVatable ? $amount : 0, 2, '.', '');
        }
        if ($netIndex !== null) {
            $cells[$netIndex] = number_format($net, 2, '.', '');
        }

        return $cells;
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, string>>
     */
    private function labeledGridRows(string $gridRaw, array $columns): array
    {
        $decoded = json_decode($gridRaw, true);
        if (! is_array($decoded)) {
            return [];
        }

        $entries = is_array($decoded['rows'] ?? null)
            ? $decoded['rows']
            : (array_is_list($decoded) ? $decoded : []);

        $rows = [];
        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $labeled = [];
            if (array_is_list($entry)) {
                foreach ($columns as $index => $label) {
                    $labeled[$label] = $this->gridCellString($entry[$index] ?? '');
                }
            } else {
                foreach ($entry as $key => $value) {
                    $labeled[(string) $key] = $this->gridCellString($value);
                }
            }

            $rows[] = $labeled;
        }

        return $rows;
    }

    private function gridCellString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_array($value)) {
            $encoded = json_encode($value);

            return is_string($encoded) ? $encoded : '';
        }

        return '';
    }

    /**
     * @param  array<string, string>  $row
     */
    private function resolveLabeledGridCell(array $row, string $label, int $index): string
    {
        foreach ([$label, strtolower($label), (string) $index] as $key) {
            if (array_key_exists($key, $row)) {
                return $this->displayGridCell(trim((string) $row[$key]));
            }
        }

        $needle = strtolower(trim($label));
        foreach ($row as $key => $value) {
            if (! is_string($key) || strtolower(trim($key)) !== $needle) {
                continue;
            }

            return $this->displayGridCell(trim((string) $value));
        }

        return '';
    }

    private function displayGridCell(string $raw): string
    {
        return $this->valueDisplay->formatStoredDateRange($raw) ?? EApprovalSiteStop::display($raw);
    }

    private function isAmountColumn(string $label): bool
    {
        return str_contains(strtolower(trim($label)), 'amount');
    }

    /**
     * @param  array<string, string>  $row
     * @param  list<string>  $columns
     */
    private function computeGridLineAmount(array $row, array $columns): string
    {
        $qty = $this->parseAmount($this->resolveLabeledGridCell($row, 'Qty', $this->columnIndex($columns, 'Qty') ?? 1));
        $unitPrice = $this->parseAmount($this->resolveLabeledGridCell($row, 'Unit price', $this->columnIndex($columns, 'Unit price') ?? 2));

        if ($qty <= 0 && $unitPrice <= 0) {
            return '';
        }

        return number_format($qty * $unitPrice, 2, '.', '');
    }

    /**
     * @param  list<string>  $columns
     */
    private function columnIndex(array $columns, string $label): ?int
    {
        $needle = strtolower(trim($label));
        foreach ($columns as $index => $column) {
            if (strtolower(trim($column)) === $needle) {
                return $index;
            }
        }

        return null;
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
