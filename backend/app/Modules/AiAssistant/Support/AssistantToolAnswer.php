<?php

declare(strict_types=1);

namespace App\Modules\AiAssistant\Support;

use App\Modules\AiAssistant\DTOs\ToolResult;

/**
 * Turns a finished catalog read into the reply, so a count does not wait on a second model call.
 */
final class AssistantToolAnswer
{
    /**
     * @param  list<ToolResult>  $results
     */
    public static function fromResults(array $results): ?string
    {
        $parts = [];
        foreach ($results as $result) {
            if (! $result->ok) {
                continue;
            }
            $rendered = self::render($result);
            if ($rendered !== null) {
                $parts[] = $rendered;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n\n", $parts);
    }

    private static function render(ToolResult $result): ?string
    {
        if ($result->tool === 'execute_sql') {
            $rows = $result->data['rows'] ?? null;
            if (is_array($rows) && $rows !== []) {
                $rendered = self::renderRows($rows);
                if ($rendered !== '') {
                    return $rendered;
                }
            }

            $summary = trim((string) ($result->summary ?? ''));

            return $summary !== '' ? $summary : null;
        }

        $summary = trim((string) ($result->summary ?? ''));

        return $summary !== '' ? $summary : null;
    }

    /**
     * @param  list<mixed>  $rows
     */
    private static function renderRows(array $rows): string
    {
        $lines = [];
        foreach (array_slice($rows, 0, 20) as $row) {
            if (! is_array($row) || $row === []) {
                continue;
            }
            if (count($row) === 1) {
                $column = (string) array_key_first($row);
                $lines[] = self::label($column).': '.self::cell($row[$column]);

                continue;
            }
            $bits = [];
            foreach ($row as $column => $value) {
                $bits[] = self::label((string) $column).': '.self::cell($value);
            }
            $lines[] = implode(' · ', $bits);
        }

        if (count($rows) > 20) {
            $lines[] = 'Showing 20 of '.count($rows).'.';
        }

        return implode("\n", $lines);
    }

    private static function label(string $column): string
    {
        $words = trim(str_replace('_', ' ', $column));

        return $words === '' ? 'Value' : ucfirst($words);
    }

    private static function cell(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '—';
    }
}
