<?php

declare(strict_types=1);

namespace App\Modules\Rollout\Support;

final class ManualTrackerSheetParser
{
    private const MIN_TRANSPOSED_FIELD_MATCHES = 8;

    private const HEADER_SCAN_LIMIT = 60;

    /**
     * @param  list<list<string|null>>  $rows
     * @return list<array<string, string>>
     */
    public function payloadsFromSheetRows(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $transposedAxes = $this->resolveTransposedAxes($rows);
        if ($transposedAxes !== null) {
            return $this->payloadsFromTransposedRows($rows, $transposedAxes);
        }

        return $this->payloadsFromRowOrientedSheet($rows);
    }

    /**
     * @param  list<array{name: string, rows: list<list<string|null>>}>  $sheets
     * @return array{sheet: string, layout: string, payloads: list<array<string, string>>, hints: list<string>}
     */
    public function payloadsFromWorkbookSheets(array $sheets): array
    {
        $best = [
            'sheet' => $sheets[0]['name'] ?? 'Sheet1',
            'layout' => 'unknown',
            'payloads' => [],
            'hints' => [],
        ];

        foreach ($sheets as $sheet) {
            $transposedAxes = $this->resolveTransposedAxes($sheet['rows']);
            $layout = $transposedAxes !== null ? 'transposed' : 'rows';
            $payloads = $this->payloadsFromSheetRows($sheet['rows']);
            $hints = $this->diagnosticHints($sheet['rows'], $layout, $transposedAxes);

            if (count($payloads) > count($best['payloads'])) {
                $best = [
                    'sheet' => $sheet['name'],
                    'layout' => $layout,
                    'payloads' => $payloads,
                    'hints' => $hints,
                ];
            } elseif (count($payloads) === 0 && count($best['payloads']) === 0 && $best['layout'] === 'unknown') {
                $best['sheet'] = $sheet['name'];
                $best['layout'] = $layout;
                $best['hints'] = $hints;
            }
        }

        return $best;
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    public function isTransposedLayout(array $rows): bool
    {
        return $this->resolveTransposedAxes($rows) !== null;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return array{labelColumn: int, valueStartColumn: int}|null
     */
    private function resolveTransposedAxes(array $rows): ?array
    {
        if ($this->findHeaderRowIndex($rows) !== null) {
            $headerRowIndex = $this->findHeaderRowIndex($rows);
            $mappedHeaders = ManualTrackerColumnMap::mapHeaders($rows[$headerRowIndex] ?? []);
            $knownKeys = array_flip(ManualTrackerColumnMap::knownImportKeys());
            $headerScore = 0;
            foreach ($mappedHeaders as $key) {
                if (isset($knownKeys[$key])) {
                    $headerScore++;
                }
            }

            if ($headerScore >= 5) {
                return null;
            }
        }

        $columnScores = [
            0 => $this->countResolvedFieldLabelsInColumn($rows, 0),
            1 => $this->countResolvedFieldLabelsInColumn($rows, 1),
        ];

        $labelColumn = array_search(max($columnScores), $columnScores, true);
        $bestScore = max($columnScores);

        if (! is_int($labelColumn) || $bestScore < self::MIN_TRANSPOSED_FIELD_MATCHES) {
            return null;
        }

        return [
            'labelColumn' => $labelColumn,
            'valueStartColumn' => $labelColumn + 1,
        ];
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @return list<array<string, string>>
     */
    private function payloadsFromRowOrientedSheet(array $rows): array
    {
        $headerRowIndex = $this->findHeaderRowIndex($rows);
        if ($headerRowIndex === null) {
            return [];
        }

        $columnMap = ManualTrackerColumnMap::mapHeaders($rows[$headerRowIndex] ?? []);
        $payloads = [];

        for ($rowIndex = $headerRowIndex + 1; $rowIndex < count($rows); $rowIndex++) {
            $payload = $this->rowToPayload($rows[$rowIndex] ?? [], $columnMap);
            if ($payload !== [] && $this->isImportablePayload($payload)) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array{labelColumn: int, valueStartColumn: int}  $axes
     * @return list<array<string, string>>
     */
    private function payloadsFromTransposedRows(array $rows, array $axes): array
    {
        /** @var array<int, string> $fieldKeysByRow */
        $fieldKeysByRow = [];

        foreach ($rows as $rowIndex => $row) {
            $fieldKey = ManualTrackerColumnMap::resolveFieldKey((string) ($row[$axes['labelColumn']] ?? ''));
            if ($fieldKey !== null) {
                $fieldKeysByRow[$rowIndex] = $fieldKey;
            }
        }

        if (! in_array('tco_site_id', $fieldKeysByRow, true)
            && ! (in_array('mno', $fieldKeysByRow, true) && in_array('project_type', $fieldKeysByRow, true))) {
            return [];
        }

        $maxColumn = $this->maxPopulatedColumn($rows, $fieldKeysByRow, $axes['valueStartColumn']);
        $payloads = [];

        for ($column = $axes['valueStartColumn']; $column <= $maxColumn; $column++) {
            $payload = [];

            foreach ($fieldKeysByRow as $rowIndex => $fieldKey) {
                $raw = trim((string) ($rows[$rowIndex][$column] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $payload[$fieldKey] = ManualTrackerValueNormalizer::normalize($fieldKey, $raw);
            }

            if ($payload !== [] && $this->isImportablePayload($payload)) {
                $payloads[] = $payload;
            }
        }

        return $payloads;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array<int, string>  $fieldKeysByRow
     */
    private function maxPopulatedColumn(array $rows, array $fieldKeysByRow, int $valueStartColumn): int
    {
        $maxColumn = $valueStartColumn;

        foreach ($rows as $row) {
            $maxColumn = max($maxColumn, count($row) - 1);
        }

        foreach (array_keys($fieldKeysByRow) as $rowIndex) {
            $row = $rows[$rowIndex] ?? [];
            for ($column = $valueStartColumn; $column < count($row); $column++) {
                if (trim((string) ($row[$column] ?? '')) !== '') {
                    $maxColumn = max($maxColumn, $column);
                }
            }
        }

        return $maxColumn;
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function findHeaderRowIndex(array $rows): ?int
    {
        $knownKeys = array_flip(ManualTrackerColumnMap::knownImportKeys());
        $bestIndex = null;
        $bestScore = 0;

        foreach (array_slice($rows, 0, self::HEADER_SCAN_LIMIT, true) as $index => $row) {
            $mappedHeaders = ManualTrackerColumnMap::mapHeaders($row);
            $score = 0;
            foreach ($mappedHeaders as $key) {
                if (isset($knownKeys[$key])) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestScore >= 3 ? $bestIndex : null;
    }

    /**
     * @param  list<list<string|null>>  $rows
     */
    private function countResolvedFieldLabelsInColumn(array $rows, int $column): int
    {
        $matches = 0;
        $limit = min(count($rows), 120);

        for ($rowIndex = 0; $rowIndex < $limit; $rowIndex++) {
            $label = (string) ($rows[$rowIndex][$column] ?? '');
            if (ManualTrackerColumnMap::resolveFieldKey($label) !== null) {
                $matches++;
            }
        }

        return $matches;
    }

    /**
     * @param  list<list<string|null>>  $rows
     * @param  array{labelColumn: int, valueStartColumn: int}|null  $transposedAxes
     * @return list<string>
     */
    private function diagnosticHints(array $rows, string $layout, ?array $transposedAxes): array
    {
        if ($layout === 'rows') {
            $headerRowIndex = $this->findHeaderRowIndex($rows);
            if ($headerRowIndex === null) {
                return ['No header row found. Row 1 should include TCO SITE ID, MNO Anchor, Project Type across columns A, B, C…'];
            }

            return [];
        }

        if ($transposedAxes === null) {
            return [];
        }

        $fieldKeysByRow = [];
        foreach ($rows as $rowIndex => $row) {
            $fieldKey = ManualTrackerColumnMap::resolveFieldKey((string) ($row[$transposedAxes['labelColumn']] ?? ''));
            if ($fieldKey !== null) {
                $fieldKeysByRow[$rowIndex] = $fieldKey;
            }
        }

        $hasValues = false;
        $maxColumn = $this->maxPopulatedColumn($rows, $fieldKeysByRow, $transposedAxes['valueStartColumn']);
        for ($column = $transposedAxes['valueStartColumn']; $column <= $maxColumn; $column++) {
            foreach ($fieldKeysByRow as $rowIndex => $fieldKey) {
                if (trim((string) ($rows[$rowIndex][$column] ?? '')) !== '') {
                    $hasValues = true;
                    break 2;
                }
            }
        }

        if (! $hasValues) {
            $label = $transposedAxes['labelColumn'] === 0 ? 'A' : 'B';
            $valueCol = $transposedAxes['valueStartColumn'] === 1 ? 'B' : 'C';

            return [
                "Found field labels in column {$label} but no site values in column {$valueCol} onward.",
                'You saved the field list only. Re-save the full tracker from Excel (609 rows or 609 columns of site data).',
            ];
        }

        return [];
    }

    /**
     * @param  list<string|null>  $row
     * @param  array<int, string>  $columnMap
     * @return array<string, string>
     */
    private function rowToPayload(array $row, array $columnMap): array
    {
        $payload = [];

        foreach ($columnMap as $index => $key) {
            $value = isset($row[$index]) ? trim((string) $row[$index]) : '';
            if ($value === '') {
                continue;
            }

            $payload[$key] = ManualTrackerValueNormalizer::normalize($key, $value);
        }

        return $payload;
    }

    /**
     * @param  array<string, string>  $payload
     */
    public function isImportablePayload(array $payload): bool
    {
        if (! empty($payload['tco_site_id'])) {
            return true;
        }

        if (! empty($payload['rollout_ref'])) {
            return true;
        }

        return ! empty($payload['mno']) && ! empty($payload['project_type']);
    }

    /**
     * @param  array<string, string>  $payload
     */
    public function isResolvablePayload(array $payload): bool
    {
        return $this->isImportablePayload($payload);
    }
}
