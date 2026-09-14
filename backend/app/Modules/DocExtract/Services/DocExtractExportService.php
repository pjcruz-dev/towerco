<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\DocExtract\Models\DocExtractBatch;
use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\ProcurementOne\Support\ProcurementExcelWorkbookWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DocExtractExportService
{
    public function exportCsv(DocExtractBatch $batch): StreamedResponse
    {
        [$headers, $rows] = $this->buildMainRows($batch);
        $filename = 'extraction-results-'.$batch->id.'.csv';

        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportXlsx(DocExtractBatch $batch): StreamedResponse
    {
        [$headers, $rows] = $this->buildMainRows($batch);
        $writer = new ProcurementExcelWorkbookWriter;
        $writer->addSheet('Extraction Results', [$headers, ...$rows]);

        foreach ($this->buildNestedTableSheets($batch) as $sheet) {
            $writer->addSheet($sheet['name'], $sheet['rows']);
        }

        $binary = $writer->toBinaryString();
        $filename = 'extraction-results-'.$batch->id.'.xlsx';

        return response()->streamDownload(function () use ($binary): void {
            echo $binary;
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return array{0: list<string>, 1: list<list<string|null>>}
     */
    private function buildMainRows(DocExtractBatch $batch): array
    {
        $batch->loadMissing(['template', 'documents']);
        $fields = app(DocExtractBatchService::class)->effectiveFields($batch);

        $headers = ['File Name'];
        foreach ($fields as $field) {
            $headers[] = (string) ($field['label'] ?? $field['key']);
        }

        $rows = [];
        /** @var DocExtractDocument $document */
        foreach ($batch->documents->sortBy('original_filename') as $document) {
            $values = is_array($document->field_values) ? $document->field_values : [];
            $row = [(string) $document->original_filename];
            foreach ($fields as $field) {
                $key = (string) $field['key'];
                $type = (string) ($field['type'] ?? 'text');
                $value = $values[$key] ?? null;
                if ($type === 'table') {
                    $row[] = $this->tableHasRows($value) ? 'View' : null;
                } else {
                    $row[] = $value === null ? null : (string) $value;
                }
            }
            $rows[] = $row;
        }

        return [$headers, $rows];
    }

    /**
     * @return list<array{name: string, rows: list<list<string|null>>}>
     */
    private function buildNestedTableSheets(DocExtractBatch $batch): array
    {
        $fields = app(DocExtractBatchService::class)->effectiveFields($batch);
        $tableFields = array_values(array_filter(
            $fields,
            static fn (array $field): bool => ($field['type'] ?? '') === 'table',
        ));
        if ($tableFields === []) {
            return [];
        }

        $sheets = [];
        $usedNames = [];

        /** @var DocExtractDocument $document */
        foreach ($batch->documents->sortBy('original_filename') as $document) {
            $values = is_array($document->field_values) ? $document->field_values : [];
            foreach ($tableFields as $field) {
                $key = (string) $field['key'];
                $parsed = $this->parseTableValue($values[$key] ?? null, $field);
                if ($parsed['rows'] === []) {
                    continue;
                }

                $baseName = pathinfo((string) $document->original_filename, PATHINFO_FILENAME);
                $label = (string) ($field['label'] ?? $key);
                $sheetName = $this->uniqueSheetName($baseName.' - '.$label, $usedNames);
                $usedNames[$sheetName] = true;

                $headers = array_map(
                    static fn (array $column): string => (string) ($column['label'] ?? $column['key']),
                    $parsed['columns'],
                );
                $sheetRows = [$headers];
                foreach ($parsed['rows'] as $row) {
                    $line = [];
                    foreach ($parsed['columns'] as $column) {
                        $columnKey = (string) $column['key'];
                        $line[] = isset($row[$columnKey]) ? (string) $row[$columnKey] : null;
                    }
                    $sheetRows[] = $line;
                }

                $sheets[] = [
                    'name' => $sheetName,
                    'rows' => $sheetRows,
                ];
            }
        }

        return $sheets;
    }

    /**
     * @param  array<string, mixed>  $field
     * @return array{columns: list<array{key: string, label: string}>, rows: list<array<string, string>>}
     */
    private function parseTableValue(mixed $raw, array $field): array
    {
        $columns = [];
        if (isset($field['columns']) && is_array($field['columns'])) {
            foreach ($field['columns'] as $column) {
                if (! is_array($column) || ! isset($column['key'])) {
                    continue;
                }
                $columns[] = [
                    'key' => (string) $column['key'],
                    'label' => (string) ($column['label'] ?? $column['key']),
                ];
            }
        }

        if ($raw === null || $raw === '') {
            return ['columns' => $columns, 'rows' => []];
        }

        $decoded = null;
        if (is_array($raw)) {
            $decoded = $raw;
        } elseif (is_string($raw)) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $decoded = $json;
            }
        }

        if (! is_array($decoded)) {
            return ['columns' => $columns, 'rows' => []];
        }

        $rowsRaw = [];
        if (array_is_list($decoded)) {
            $rowsRaw = $decoded;
        } elseif (isset($decoded['rows']) && is_array($decoded['rows'])) {
            $rowsRaw = $decoded['rows'];
            if ($columns === [] && isset($decoded['columns']) && is_array($decoded['columns'])) {
                foreach ($decoded['columns'] as $column) {
                    if (! is_array($column) || ! isset($column['key'])) {
                        continue;
                    }
                    $columns[] = [
                        'key' => (string) $column['key'],
                        'label' => (string) ($column['label'] ?? $column['key']),
                    ];
                }
            }
        }

        $rows = [];
        foreach ($rowsRaw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $normalized = [];
            if ($columns !== []) {
                foreach ($columns as $column) {
                    $key = $column['key'];
                    $value = $row[$key] ?? $row[$column['label']] ?? null;
                    $normalized[$key] = $value === null ? '' : (string) $value;
                }
            } else {
                foreach ($row as $key => $value) {
                    $normalized[(string) $key] = $value === null ? '' : (string) $value;
                }
            }
            $rows[] = $normalized;
        }

        if ($columns === [] && $rows !== []) {
            foreach (array_keys($rows[0]) as $key) {
                $columns[] = ['key' => $key, 'label' => $key];
            }
        }

        return ['columns' => $columns, 'rows' => $rows];
    }

    private function tableHasRows(mixed $raw): bool
    {
        return $this->parseTableValue($raw, [])['rows'] !== [];
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueSheetName(string $name, array $used): string
    {
        $clean = preg_replace('/[\\\\\/\?\*\[\]\:]/', '-', $name) ?? $name;
        $clean = trim($clean);
        if ($clean === '') {
            $clean = 'Sheet';
        }
        $clean = mb_substr($clean, 0, 28);
        $candidate = $clean;
        $index = 2;
        while (isset($used[$candidate])) {
            $suffix = ' '.$index;
            $candidate = mb_substr($clean, 0, 31 - mb_strlen($suffix)).$suffix;
            $index++;
        }

        return $candidate;
    }
}
