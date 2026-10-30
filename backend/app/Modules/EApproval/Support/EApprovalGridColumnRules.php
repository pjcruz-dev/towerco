<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Support;

/**
 * Column flags the form builder stores on a grid. Hidden columns stay in the
 * saved row so cell indexes do not move, and a hidden required column cannot
 * block a line the requestor can no longer fill.
 */
final class EApprovalGridColumnRules
{
    /**
     * @param  array<string, mixed>|null  $options
     */
    public static function requiredError(?array $options, mixed $raw): ?string
    {
        $columns = is_array($options['columns'] ?? null) ? $options['columns'] : [];
        $required = [];
        foreach ($columns as $index => $column) {
            if (! is_array($column) || empty($column['required']) || ! empty($column['hidden'])) {
                continue;
            }
            $label = trim((string) ($column['label'] ?? ''));
            if ($label !== '') {
                $required[(int) $index] = $label;
            }
        }
        if ($required === []) {
            return null;
        }

        foreach (self::rows($raw) as $row) {
            if (! is_array($row) || ! self::rowHasContent($row)) {
                continue;
            }
            foreach ($required as $index => $label) {
                $cell = trim((string) ($row[(string) $index] ?? $row[$index] ?? ''));
                if ($cell === '') {
                    return __(':label is required on each line.', ['label' => $label]);
                }
            }
        }

        return null;
    }

    /**
     * @param  array<mixed>  $row
     */
    private static function rowHasContent(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<mixed>
     */
    private static function rows(mixed $raw): array
    {
        if (is_array($raw)) {
            if (array_is_list($raw)) {
                return $raw;
            }
            $rows = $raw['rows'] ?? null;

            return is_array($rows) ? array_values($rows) : [];
        }
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (! is_array($decoded)) {
            return [];
        }
        $rows = $decoded['rows'] ?? null;

        return is_array($rows) ? array_values($rows) : (array_is_list($decoded) ? $decoded : []);
    }
}
