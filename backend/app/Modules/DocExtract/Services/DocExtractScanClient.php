<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

final class DocExtractScanClient
{
    /**
     * @param  list<array{key?: mixed, label?: mixed, type?: mixed, hint?: mixed}>  $fields
     * @param  list<int>|null  $pages  1-based page numbers to scan; null = all pages
     * @return array{
     *   engine: string,
     *   pages: list<array{page: int, text: string}>,
     *   warnings: list<string>,
     *   field_values: array<string, string|null>,
     *   discovered_fields: list<array{key: string, label: string, type: string, hint: string|null, columns?: list<array<string, mixed>>}>,
     *   mode: string
     * }
     */
    public function scan(
        string $filename,
        string $mimeType,
        string $binaryContents,
        array $fields = [],
        ?array $pages = null,
    ): array {
        $url = config('doc_extract.service_url').'/v1/scan';
        $timeout = max(10, (int) config('doc_extract.timeout_seconds', 120));

        $form = [
            'mime_type' => $mimeType,
            'fields_json' => json_encode(array_values($fields), JSON_THROW_ON_ERROR),
        ];
        $normalizedPages = $this->normalizePageList($pages);
        if ($normalizedPages !== null) {
            $form['pages'] = implode(',', $normalizedPages);
        }

        $response = Http::timeout($timeout)
            ->attach('file', $binaryContents, $filename)
            ->post($url, $form);

        if (! $response->successful()) {
            throw new RuntimeException(
                'DocExtract scan service failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        return $this->normalizeScanPayload($payload, $fields);
    }

    public function pageCount(string $filename, string $mimeType, string $binaryContents): int
    {
        $url = config('doc_extract.service_url').'/v1/page-count';
        $timeout = max(10, (int) config('doc_extract.timeout_seconds', 120));

        $response = Http::timeout($timeout)
            ->attach('file', $binaryContents, $filename)
            ->post($url, [
                'mime_type' => $mimeType,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'DocExtract page-count failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        $count = (int) ($response->json('page_count') ?? 0);

        return max(1, $count);
    }

    /**
     * @return array{
     *   page_count: int,
     *   pages: list<array{page: int, thumbnail: string|null, text_chars: int, likely_blank: bool}>
     * }
     */
    public function preview(string $filename, string $mimeType, string $binaryContents): array
    {
        $url = config('doc_extract.service_url').'/v1/preview';
        $timeout = max(30, (int) config('doc_extract.timeout_seconds', 120));

        $response = Http::timeout($timeout)
            ->attach('file', $binaryContents, $filename)
            ->post($url, [
                'mime_type' => $mimeType,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'DocExtract preview failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $pageCount = max(1, (int) ($payload['page_count'] ?? 1));
        $pages = [];
        if (isset($payload['pages']) && is_array($payload['pages'])) {
            foreach ($payload['pages'] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $pages[] = [
                    'page' => (int) ($row['page'] ?? count($pages) + 1),
                    'thumbnail' => isset($row['thumbnail']) && is_string($row['thumbnail']) && $row['thumbnail'] !== ''
                        ? $row['thumbnail']
                        : null,
                    'text_chars' => (int) ($row['text_chars'] ?? 0),
                    'likely_blank' => (bool) ($row['likely_blank'] ?? false),
                ];
            }
        }

        if ($pages === []) {
            $pages[] = [
                'page' => 1,
                'thumbnail' => null,
                'text_chars' => 0,
                'likely_blank' => false,
            ];
        }

        return [
            'page_count' => $pageCount,
            'pages' => $pages,
        ];
    }

    /**
     * Map OCR text onto a field schema without re-running OCR.
     *
     * @param  list<array{key?: mixed, label?: mixed, type?: mixed, hint?: mixed, columns?: mixed}>  $fields
     * @return array<string, string|null>
     */
    public function mapText(string $extractedText, array $fields): array
    {
        if ($fields === [] || trim($extractedText) === '') {
            return [];
        }

        $url = config('doc_extract.service_url').'/v1/map';
        $timeout = max(10, (int) config('doc_extract.timeout_seconds', 120));

        $response = Http::timeout($timeout)->asForm()->post($url, [
            'text' => $extractedText,
            'fields_json' => json_encode(array_values($fields), JSON_THROW_ON_ERROR),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'DocExtract map service failed: HTTP '.$response->status().' '.$response->body(),
            );
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
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
                if (is_array($value)) {
                    try {
                        $fieldValues[$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                    } catch (\JsonException) {
                        $fieldValues[$key] = null;
                    }
                }
            }
        }

        if ($fieldValues === []) {
            return app(DocExtractFieldMapper::class)->map($fields, $extractedText);
        }

        return $fieldValues;
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

    /**
     * @param  list<int>|null  $pages
     * @return list<int>|null
     */
    private function normalizePageList(?array $pages): ?array
    {
        if ($pages === null || $pages === []) {
            return null;
        }

        $normalized = [];
        foreach ($pages as $page) {
            $value = (int) $page;
            if ($value < 1) {
                continue;
            }
            $normalized[$value] = $value;
        }

        if ($normalized === []) {
            return null;
        }

        $list = array_values($normalized);
        sort($list);

        return $list;
    }

    /**
     * @param  array<string, mixed>  $payload
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
    private function normalizeScanPayload(array $payload, array $fields): array
    {
        $pagesRaw = $payload['pages'] ?? [];
        $pages = [];
        if (is_array($pagesRaw)) {
            foreach ($pagesRaw as $pageRow) {
                if (! is_array($pageRow)) {
                    continue;
                }
                $pages[] = [
                    'page' => (int) ($pageRow['page'] ?? count($pages) + 1),
                    'text' => (string) ($pageRow['text'] ?? ''),
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

        if ($fieldValues === [] && $fields !== []) {
            $pageTexts = array_map(static fn (array $pageRow): string => trim($pageRow['text']), $pages);
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
}
