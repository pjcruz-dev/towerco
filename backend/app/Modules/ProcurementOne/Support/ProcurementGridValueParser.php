<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

/**
 * Normalizes E-Approval grid field payloads (indexed rows or labeled rows).
 */
final class ProcurementGridValueParser
{
    /**
     * @param  list<string>  $columnLabels
     * @return list<array<string, string>>
     */
    public function labeledRows(mixed $raw, array $columnLabels): array
    {
        $labeled = [];

        foreach ($this->extractRows($raw) as $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($this->isIndexedRow($row)) {
                $mapped = [];
                foreach ($columnLabels as $index => $label) {
                    $mapped[$label] = trim((string) ($row[(string) $index] ?? $row[$index] ?? ''));
                }
                $labeled[] = $mapped;

                continue;
            }

            $normalized = [];
            foreach ($row as $key => $value) {
                if (! is_scalar($value)) {
                    continue;
                }

                $normalized[(string) $key] = trim((string) $value);
            }

            if ($normalized !== []) {
                $labeled[] = $normalized;
            }
        }

        return $labeled;
    }

    /**
     * @return list<mixed>
     */
    public function extractRows(mixed $raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            try {
                $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                return [];
            }
        }

        if (! is_array($raw)) {
            return [];
        }

        if (isset($raw['rows']) && is_array($raw['rows'])) {
            return array_values($raw['rows']);
        }

        if (array_is_list($raw)) {
            return $raw;
        }

        return [];
    }

    /**
     * @param  array<mixed, mixed>  $row
     */
    private function isIndexedRow(array $row): bool
    {
        foreach (array_keys($row) as $key) {
            if (is_int($key) || (is_string($key) && ctype_digit($key))) {
                continue;
            }

            return false;
        }

        return $row !== [];
    }
}
