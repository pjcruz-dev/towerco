<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

/**
 * Heuristic label/value extraction from OCR text (no LLM).
 * Human review remains the source of truth for finance accuracy.
 */
final class DocExtractFieldMapper
{
    /**
     * @param  list<array{key?: mixed, label?: mixed, type?: mixed, hint?: mixed}>  $fields
     * @return array<string, string|null>
     */
    public function map(array $fields, string $extractedText): array
    {
        $values = [];
        $normalized = $this->normalizeWhitespace($extractedText);

        foreach ($fields as $field) {
            $key = trim((string) ($field['key'] ?? ''));
            if ($key === '') {
                continue;
            }

            $label = trim((string) ($field['label'] ?? $key));
            $hint = trim((string) ($field['hint'] ?? ''));
            $values[$key] = $this->findValue($normalized, $label, $hint);
        }

        return $values;
    }

    private function findValue(string $text, string $label, string $hint): ?string
    {
        $needles = array_values(array_filter([$label, $hint, $this->humanizeKey($label)]));
        foreach ($needles as $needle) {
            if ($needle === '') {
                continue;
            }
            $pattern = '/'.preg_quote($needle, '/').'\s*[:\-]\s*(.+?)(?:\r?\n|$)/iu';
            if (preg_match($pattern, $text, $matches) === 1) {
                $value = $this->normalizeWhitespace($matches[1] ?? '');
                if ($value !== '') {
                    return mb_substr($value, 0, 500);
                }
            }
        }

        return null;
    }

    private function humanizeKey(string $value): string
    {
        return str_replace(['_', '-'], ' ', $value);
    }

    private function normalizeWhitespace(string $value): string
    {
        $trimmed = trim(preg_replace("/[ \t]+/u", ' ', $value) ?? $value);

        return preg_replace("/\r\n|\r/u", "\n", $trimmed) ?? $trimmed;
    }
}
