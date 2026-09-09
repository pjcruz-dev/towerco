<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DocExtractScanClient
{
    /**
     * @param  list<array{key?: mixed, label?: mixed, type?: mixed, hint?: mixed}>  $fields
     * @return array{
     *   engine: string,
     *   pages: list<array{page: int, text: string}>,
     *   warnings: list<string>,
     *   field_values: array<string, string|null>,
     *   discovered_fields: list<array{key: string, label: string, type: string, hint: string|null, columns?: list<array<string, mixed>>}>,
     *   mode: string
     * }
     */
    public function scan(string $filename, string $mimeType, string $binaryContents, array $fields = []): array
    {
        $url = config('doc_extract.service_url').'/v1/scan';
        $timeout = max(10, (int) config('doc_extract.timeout_seconds', 120));

        $response = Http::timeout($timeout)
            ->attach('file', $binaryContents, $filename)
            ->post($url, [
                'mime_type' => $mimeType,
                'fields_json' => json_encode(array_values($fields), JSON_THROW_ON_ERROR),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'DocExtract scan service failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $pagesRaw = $payload['pages'] ?? [];
        $pages = [];
        if (is_array($pagesRaw)) {
            foreach ($pagesRaw as $page) {
                if (! is_array($page)) {
                    continue;
                }
                $pages[] = [
                    'page' => (int) ($page['page'] ?? count($pages) + 1),
                    'text' => (string) ($page['text'] ?? ''),
                ];
            }
        }

        $warnings = [];
        if (isset($payload['warnings']) && is_array($payload['warnings'])) {
            foreach ($payload['warnings'] as $warning) {
                if (is_string($warning) && $warning !== '') {
                    $warnings[] = $warning;
                }
            }
        }

        $fieldValues = [];
        if (isset($payload['field_values']) && is_array($payload['field_values'])) {
            foreach ($payload['field_values'] as $key => $value) {
                if (! is_string($key) || $key === '') {
                    continue;
                }
                if ($value === null) {
                    $fieldValues[$key] = null;
                    continue;
                }
                if (is_scalar($value)) {
                    $fieldValues[$key] = (string) $value;
                    continue;
                }
                // Nested table payloads arrive as objects/arrays — store as JSON string.
                if (is_array($value)) {
                    try {
                        $fieldValues[$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    } catch (\JsonException) {
                        $fieldValues[$key] = null;
                    }
                }
            }
        }

        $discoveredFields = [];
        if (isset($payload['discovered_fields']) && is_array($payload['discovered_fields'])) {
            foreach ($payload['discovered_fields'] as $field) {
                if (! is_array($field) || ! isset($field['key'])) {
                    continue;
                }
                $key = trim((string) $field['key']);
                if ($key === '') {
                    continue;
                }
                $row = [
                    'key' => $key,
                    'label' => trim((string) ($field['label'] ?? $key)),
                    'type' => strtolower(trim((string) ($field['type'] ?? 'text'))),
                    'hint' => isset($field['hint']) && is_string($field['hint']) && $field['hint'] !== ''
                        ? $field['hint']
                        : null,
                ];
                if (isset($field['columns']) && is_array($field['columns']) && $field['columns'] !== []) {
                    $row['columns'] = $field['columns'];
                }
                $discoveredFields[] = $row;
            }
        }

        // Fallback to PHP heuristics if the sidecar returned no mapped values for a template.
        if ($fieldValues === [] && $fields !== []) {
            $pageTexts = array_map(static fn (array $page): string => trim($page['text']), $pages);
            $extractedText = trim(implode("\n\n", array_filter($pageTexts, static fn (string $t): bool => $t !== '')));
            $fieldValues = app(DocExtractFieldMapper::class)->map($fields, $extractedText);
        }

        return [
            'engine' => (string) ($payload['engine'] ?? 'unknown'),
            'pages' => $pages,
            'warnings' => $warnings,
            'field_values' => $fieldValues,
            'discovered_fields' => $discoveredFields,
            'mode' => (string) ($payload['mode'] ?? ($fields !== [] ? 'template' : 'auto')),
        ];
    }

    public function health(): bool
    {
        try {
            $response = Http::timeout(5)->get(config('doc_extract.service_url').'/health');

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
