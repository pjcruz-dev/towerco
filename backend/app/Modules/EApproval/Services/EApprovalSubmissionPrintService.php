<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalFieldOptionsParser;

final class EApprovalSubmissionPrintService
{
    private const DEDICATED_PRINT_TEMPLATE_KINDS = [
        'purchase_requisition',
        'purchase_order',
    ];

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
            ];
        }

        $formMetadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        $printTemplateKind = (string) ($template['layout_kind'] ?? $formMetadata['print_template_kind'] ?? '');

        $fields = $this->resolvePrintFields($valuesByKey, $layoutRows, $layoutPersisted, $printTemplateKind);

        return [
            'document_no' => $submission->document_no,
            'form_name' => $submission->form?->name,
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
            'print_template_kind' => $printTemplateKind !== '' ? $printTemplateKind : null,
            'fields' => $fields,
            'grids' => $this->buildGridPrintModels($submission),
            'approvals' => $submission->approvals->map(static fn ($a) => [
                'step' => $a->step?->step_order,
                'approver' => $a->approver?->name,
                'status' => $a->status,
                'remarks' => $a->remarks,
                'signature' => $a->signature,
                'acted_at' => $a->acted_at?->format('Y-m-d H:i'),
            ])->values()->all(),
            'attachments' => $submission->attachments->map(static fn ($a) => [
                'id' => (string) $a->id,
                'file_name' => $a->file_name,
                'field_name' => $a->field_name,
                'metadata' => is_array($a->metadata) ? $a->metadata : null,
            ])->values()->all(),
            'template' => $template,
            'show_approval_trail' => count($submission->approvals) > 0,
        ];
    }

    /**
     * @param  array<string, array{label: string, value: string|null}>  $valuesByKey
     * @param  list<mixed>  $layoutRows
     * @return list<array{key: string, label: string, value: string|null}>
     */
    private function resolvePrintFields(array $valuesByKey, array $layoutRows, bool $layoutPersisted, string $printTemplateKind): array
    {
        if ($this->usesDedicatedPrintTemplate($printTemplateKind) || ! $layoutPersisted) {
            return $this->allPrintFields($valuesByKey);
        }

        $fields = [];
        foreach ($layoutRows as $row) {
            if (! is_array($row) || empty($row['visible'])) {
                continue;
            }
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! isset($valuesByKey[$key])) {
                continue;
            }
            $fields[] = [
                'key' => $key,
                'label' => (string) ($row['label'] ?? $valuesByKey[$key]['label']),
                'value' => $valuesByKey[$key]['value'],
            ];
        }

        return $fields === [] ? $this->allPrintFields($valuesByKey) : $fields;
    }

    /**
     * @param  array<string, array{label: string, value: string|null}>  $valuesByKey
     * @return list<array{key: string, label: string, value: string|null}>
     */
    private function allPrintFields(array $valuesByKey): array
    {
        return array_map(
            static fn (array $row, string $key) => [
                'key' => $key,
                'label' => $row['label'],
                'value' => $row['value'],
            ],
            $valuesByKey,
            array_keys($valuesByKey),
        );
    }

    private function usesDedicatedPrintTemplate(string $printTemplateKind): bool
    {
        return in_array($printTemplateKind, self::DEDICATED_PRINT_TEMPLATE_KINDS, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildGridPrintModels(EApprovalSubmission $submission): array
    {
        $grids = [];

        foreach ($submission->values as $row) {
            if ((string) ($row->field?->type ?? '') !== 'grid') {
                continue;
            }

            $key = (string) ($row->field?->name ?? $row->field_id);
            $options = is_array($row->field?->options) ? $row->field->options : [];
            $columns = EApprovalFieldOptionsParser::gridColumns($options);
            if ($columns === []) {
                continue;
            }

            $raw = is_scalar($row->value) ? trim((string) $row->value) : '';
            $parsedRows = $this->parseGridRowsForPrint($raw, $columns);
            if ($parsedRows === []) {
                continue;
            }

            $grids[] = [
                'key' => $key,
                'label' => (string) ($row->field?->label ?? $key),
                'columns' => $columns,
                'rows' => $parsedRows,
            ];
        }

        return $grids;
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
                if ($value === '' && str_contains(strtolower(trim($label)), 'amount')) {
                    $qty = $this->parseAmount($this->resolveLabeledGridCell($row, 'Qty', $this->columnIndex($columns, 'Qty') ?? 1));
                    $unitPrice = $this->parseAmount($this->resolveLabeledGridCell($row, 'Unit price', $this->columnIndex($columns, 'Unit price') ?? 2));
                    $value = ($qty <= 0 && $unitPrice <= 0) ? '' : number_format($qty * $unitPrice, 2, '.', '');
                }
                if ($value !== '') {
                    $hasContent = true;
                }
                $cells[] = $value;
            }

            if ($hasContent) {
                $normalized[] = $cells;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, string>>
     */
    private function labeledGridRows(string $gridRaw, array $columns): array
    {
        try {
            $decoded = json_decode($gridRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $rows = is_array($decoded['rows'] ?? null) ? $decoded['rows'] : (is_array($decoded) ? $decoded : []);
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $labeled = [];
            foreach ($columns as $index => $label) {
                $value = $row[$label] ?? $row[(string) $index] ?? $row[$index] ?? '';
                $labeled[$label] = is_scalar($value) ? trim((string) $value) : '';
            }
            $out[] = $labeled;
        }

        return $out;
    }

    /** @param  array<string, string>  $row */
    private function resolveLabeledGridCell(array $row, string $label, int $index): string
    {
        foreach ([$label, strtolower($label), (string) $index] as $key) {
            if (array_key_exists($key, $row)) {
                return trim((string) $row[$key]);
            }
        }

        $needle = strtolower(trim($label));
        foreach ($row as $key => $value) {
            if (is_string($key) && strtolower(trim($key)) === $needle) {
                return trim((string) $value);
            }
        }

        return '';
    }

    /** @param  list<string>  $columns */
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
