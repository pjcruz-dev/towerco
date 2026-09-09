<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Modules\DocExtract\Jobs\ProcessDocExtractDocumentJob;
use App\Modules\DocExtract\Models\DocExtractBatch;
use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\DocExtract\Models\DocExtractTemplate;
use App\Modules\DocExtract\Support\DocExtractBatchStatus;
use App\Modules\DocExtract\Support\DocExtractDocumentStatus;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocExtractBatchService
{
    public function __construct(
        private readonly DocExtractFileStorageService $storage,
        private readonly DocExtractTemplateService $templates,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $paginator = DocExtractBatch::query()
            ->with('template:id,name')
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $perPage)), ['*'], 'page', max(1, $page));

        $rows = [];
        foreach ($paginator->items() as $batch) {
            /** @var DocExtractBatch $batch */
            $this->reconcileStaleProcessingStatus($batch);
            $rows[] = $this->asListRow($batch);
        }

        return [
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }

    public function findOrFail(string $id): DocExtractBatch
    {
        $batch = DocExtractBatch::query()
            ->with(['template', 'documents'])
            ->findOrFail($id);
        $this->reconcileStaleProcessingStatus($batch);

        return $batch;
    }

    /**
     * @param  list<UploadedFile>  $files
     */
    public function create(?string $templateId, array $files, TenantUser $actor): DocExtractBatch
    {
        $maxFiles = max(1, (int) config('doc_extract.max_files_per_batch', 25));
        if ($files === []) {
            throw ValidationException::withMessages([
                'files' => [__('Upload at least one PDF or image.')],
            ]);
        }
        if (count($files) > $maxFiles) {
            throw ValidationException::withMessages([
                'files' => [__('A batch may include at most :max files.', ['max' => $maxFiles])],
            ]);
        }

        $template = null;
        if ($templateId !== null && $templateId !== '') {
            $template = $this->templates->findPublishedOrFail($templateId);
        }

        $batchId = (string) Str::uuid();

        $batch = DocExtractBatch::query()->create([
            'id' => $batchId,
            'template_id' => $template?->id,
            // Mark processing before dispatch: with QUEUE_CONNECTION=sync jobs finish
            // inside dispatch() and refresh counters to ready/failed; never overwrite after.
            'status' => DocExtractBatchStatus::PROCESSING,
            'document_count' => count($files),
            'ready_count' => 0,
            'failed_count' => 0,
            'created_by_id' => $actor->id,
        ]);

        $tenantId = (string) (tenant('id') ?? '');

        foreach ($files as $file) {
            $meta = $this->storage->store($batchId, $file);
            $document = DocExtractDocument::query()->create([
                'id' => (string) Str::uuid(),
                'batch_id' => $batch->id,
                'template_id' => $template?->id,
                'original_filename' => $meta['original_filename'],
                'stored_path' => $meta['stored_path'],
                'mime_type' => $meta['mime_type'],
                'size_bytes' => $meta['size_bytes'],
                'status' => DocExtractDocumentStatus::PENDING,
                'field_values' => $template !== null ? $this->emptyFieldValues($template) : [],
            ]);

            ProcessDocExtractDocumentJob::dispatch($tenantId, (string) $document->id);
        }

        // Re-derive status from document rows (handles sync queue race + partial failures).
        $batch->refresh();
        $this->refreshBatchCounters($batch);

        return $this->findOrFail((string) $batch->id);
    }

    /**
     * Persist the curated (or auto-detected) field list as a reusable template.
     * Prefer same-layout batches: remove unwanted columns first, then save.
     */
    public function saveDiscoveredTemplate(DocExtractBatch $batch, string $name, ?string $description, TenantUser $actor): DocExtractTemplate
    {
        $fields = $this->effectiveFields($batch);
        if ($fields === []) {
            throw ValidationException::withMessages([
                'fields' => [__('No fields left. Keep at least one column, or wait for scans to finish.')],
            ]);
        }

        return $this->templates->create([
            'name' => $name,
            'description' => $description,
            'fields' => $fields,
            'status' => DocExtractTemplateStatus::DRAFT,
        ], $actor);
    }

    /**
     * Replace the curated column list for an auto-detect batch (remove / reorder fields).
     *
     * @param  list<array{key?: mixed, label?: mixed, type?: mixed, hint?: mixed}>  $fields
     * @return list<array{key: string, label: string, type: string, hint: string|null}>
     */
    public function updateFieldSchema(DocExtractBatch $batch, array $fields): array
    {
        $normalized = [];
        $seen = [];
        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }
            $key = Str::slug(trim((string) ($field['key'] ?? '')), '_');
            if ($key === '') {
                $key = Str::slug(trim((string) ($field['label'] ?? '')), '_');
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $label = trim((string) ($field['label'] ?? $key));
            $type = strtolower(trim((string) ($field['type'] ?? 'text')));
            $allowedTypes = [
                'text', 'multiline', 'number', 'currency', 'percentage',
                'date', 'email', 'phone', 'boolean', 'table',
            ];
            if (! in_array($type, $allowedTypes, true)) {
                $type = 'text';
            }
            $hint = trim((string) ($field['hint'] ?? ''));
            $description = trim((string) ($field['description'] ?? ''));
            $row = [
                'key' => $key,
                'label' => $label !== '' ? $label : $key,
                'type' => $type,
                'hint' => $hint !== '' ? $hint : null,
                'description' => $description !== '' ? mb_substr($description, 0, 500) : null,
            ];
            if ($type === 'table' && isset($field['columns']) && is_array($field['columns'])) {
                $row['columns'] = array_values($field['columns']);
            }
            $normalized[] = $row;
        }

        if ($normalized === []) {
            throw ValidationException::withMessages([
                'fields' => [__('Keep at least one column.')],
            ]);
        }

        $batch->field_schema = $normalized;
        $batch->save();

        return $normalized;
    }

    /**
     * Drop one discovered column from the batch review grid.
     *
     * @return list<array{key: string, label: string, type: string, hint: string|null}>
     */
    public function removeField(DocExtractBatch $batch, string $fieldKey): array
    {
        $current = $this->effectiveFields($batch);
        $remaining = array_values(array_filter(
            $current,
            static fn (array $field): bool => (string) $field['key'] !== $fieldKey,
        ));

        return $this->updateFieldSchema($batch, $remaining);
    }

    /**
     * @param  array<string, mixed>  $fieldValues
     */
    public function updateDocumentFields(DocExtractDocument $document, array $fieldValues): DocExtractDocument
    {
        $template = $document->template ?? ($document->template_id
            ? DocExtractTemplate::query()->find($document->template_id)
            : null);
        $batch = $document->batch ?? DocExtractBatch::query()->find($document->batch_id);
        $allowedKeys = [];
        if ($batch !== null) {
            foreach ($this->effectiveFields($batch) as $field) {
                $allowedKeys[(string) $field['key']] = true;
            }
        }
        if ($allowedKeys === [] && $template !== null && is_array($template->fields)) {
            foreach ($template->fields as $field) {
                if (is_array($field) && isset($field['key'])) {
                    $allowedKeys[(string) $field['key']] = true;
                }
            }
        }
        if ($allowedKeys === []) {
            foreach (array_keys(is_array($document->field_values) ? $document->field_values : []) as $key) {
                $allowedKeys[(string) $key] = true;
            }
            foreach (array_keys($fieldValues) as $key) {
                $allowedKeys[(string) $key] = true;
            }
        }

        $normalized = is_array($document->field_values) ? $document->field_values : [];
        foreach ($fieldValues as $key => $value) {
            $key = (string) $key;
            if ($allowedKeys !== [] && ! isset($allowedKeys[$key])) {
                continue;
            }
            if ($value === null) {
                $normalized[$key] = null;
            } elseif (is_scalar($value)) {
                $normalized[$key] = trim((string) $value);
            } elseif (is_array($value)) {
                try {
                    $normalized[$key] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
                } catch (\JsonException) {
                    $normalized[$key] = null;
                }
            } else {
                $normalized[$key] = null;
            }
        }

        $document->field_values = $normalized;
        if ($document->status === DocExtractDocumentStatus::FAILED) {
            $document->status = DocExtractDocumentStatus::READY;
            $document->error_message = null;
        }
        $document->save();

        return $document->fresh() ?? $document;
    }

    public function refreshBatchCounters(DocExtractBatch $batch): void
    {
        $ready = $batch->documents()->where('status', DocExtractDocumentStatus::READY)->count();
        $failed = $batch->documents()->where('status', DocExtractDocumentStatus::FAILED)->count();
        $pending = $batch->documents()
            ->whereIn('status', [DocExtractDocumentStatus::PENDING, DocExtractDocumentStatus::SCANNING])
            ->count();

        $batch->ready_count = $ready;
        $batch->failed_count = $failed;
        $batch->document_count = $batch->documents()->count();

        if ($pending > 0) {
            $batch->status = DocExtractBatchStatus::PROCESSING;
        } elseif ($failed > 0 && $ready === 0) {
            $batch->status = DocExtractBatchStatus::FAILED;
            $batch->message = 'All documents failed to scan.';
        } else {
            $batch->status = DocExtractBatchStatus::READY;
            $batch->message = null;
        }

        $batch->save();
    }

    /**
     * Heal batches left as "processing" after documents finished (sync-queue race).
     */
    private function reconcileStaleProcessingStatus(DocExtractBatch $batch): void
    {
        if ((string) $batch->status !== DocExtractBatchStatus::PROCESSING) {
            return;
        }

        $terminal = (int) $batch->ready_count + (int) $batch->failed_count;
        if ((int) $batch->document_count > 0 && $terminal >= (int) $batch->document_count) {
            $this->refreshBatchCounters($batch);

            return;
        }

        // Counters may also be stale — verify against document rows once.
        $pending = $batch->documents()
            ->whereIn('status', [DocExtractDocumentStatus::PENDING, DocExtractDocumentStatus::SCANNING])
            ->count();
        if ($pending === 0 && (int) $batch->document_count > 0) {
            $this->refreshBatchCounters($batch);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function asListRow(DocExtractBatch $batch): array
    {
        $mode = $batch->template_id ? 'template' : 'auto';

        return [
            'id' => (string) $batch->id,
            'status' => (string) $batch->status,
            'mode' => $mode,
            'document_count' => (int) $batch->document_count,
            'ready_count' => (int) $batch->ready_count,
            'failed_count' => (int) $batch->failed_count,
            'message' => $batch->message,
            'template_id' => $batch->template_id ? (string) $batch->template_id : null,
            'template_name' => $batch->template?->name ?? ($mode === 'auto' ? 'Auto-detect' : null),
            'created_at' => optional($batch->created_at)?->toIso8601String(),
            'updated_at' => optional($batch->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asDetail(DocExtractBatch $batch): array
    {
        $batch->loadMissing(['template', 'documents']);
        $documents = $batch->documents
            ->sortBy('original_filename')
            ->values()
            ->map(fn (DocExtractDocument $document): array => $this->asDocumentRow($document))
            ->all();

        $effectiveFields = $this->effectiveFields($batch);

        return [
            ...$this->asListRow($batch),
            'template' => $batch->template ? $this->templates->asRow($batch->template) : null,
            'effective_fields' => $effectiveFields,
            'documents' => $documents,
        ];
    }

    /**
     * Columns for review/export: curated schema, template fields, or union of OCR discoveries.
     *
     * @return list<array{key: string, label: string, type: string, hint: string|null}>
     */
    public function effectiveFields(DocExtractBatch $batch): array
    {
        $batch->loadMissing(['template', 'documents']);

        if (is_array($batch->field_schema) && $batch->field_schema !== []) {
            $normalized = [];
            foreach ($batch->field_schema as $field) {
                if (! is_array($field) || ! isset($field['key'])) {
                    continue;
                }
                $row = [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? $field['key']),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'hint' => isset($field['hint']) && is_string($field['hint']) ? $field['hint'] : null,
                ];
                if (isset($field['columns']) && is_array($field['columns']) && $field['columns'] !== []) {
                    $row['columns'] = $field['columns'];
                }
                $normalized[] = $row;
            }
            if ($normalized !== []) {
                return $normalized;
            }
        }

        if ($batch->template !== null && is_array($batch->template->fields) && $batch->template->fields !== []) {
            $normalized = [];
            foreach ($batch->template->fields as $field) {
                if (! is_array($field) || ! isset($field['key'])) {
                    continue;
                }
                $row = [
                    'key' => (string) $field['key'],
                    'label' => (string) ($field['label'] ?? $field['key']),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'hint' => isset($field['hint']) && is_string($field['hint']) ? $field['hint'] : null,
                ];
                if (isset($field['columns']) && is_array($field['columns']) && $field['columns'] !== []) {
                    $row['columns'] = $field['columns'];
                }
                $normalized[] = $row;
            }

            return $normalized;
        }

        $byKey = [];
        foreach ($batch->documents as $document) {
            $meta = is_array($document->scan_meta) ? $document->scan_meta : [];
            $discovered = $meta['discovered_fields'] ?? [];
            if (is_array($discovered)) {
                foreach ($discovered as $field) {
                    if (! is_array($field) || ! isset($field['key'])) {
                        continue;
                    }
                    $key = (string) $field['key'];
                    if ($key === '' || isset($byKey[$key])) {
                        continue;
                    }
                    $row = [
                        'key' => $key,
                        'label' => (string) ($field['label'] ?? $key),
                        'type' => (string) ($field['type'] ?? 'text'),
                        'hint' => isset($field['hint']) && is_string($field['hint']) ? $field['hint'] : null,
                    ];
                    if (isset($field['columns']) && is_array($field['columns']) && $field['columns'] !== []) {
                        $row['columns'] = $field['columns'];
                    }
                    $byKey[$key] = $row;
                }
            }

            $values = is_array($document->field_values) ? $document->field_values : [];
            foreach ($values as $key => $value) {
                $key = (string) $key;
                if ($key === '' || isset($byKey[$key])) {
                    continue;
                }
                $looksLikeTable = is_string($value)
                    && str_contains($value, '"rows"')
                    && (str_starts_with(ltrim($value), '{') || str_starts_with(ltrim($value), '['));
                $byKey[$key] = [
                    'key' => $key,
                    'label' => Str::title(str_replace('_', ' ', $key)),
                    'type' => $looksLikeTable ? 'table' : 'text',
                    'hint' => null,
                ];
            }
        }

        return array_values($byKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function asDocumentRow(DocExtractDocument $document): array
    {
        $meta = is_array($document->scan_meta) ? $document->scan_meta : [];
        $discovered = [];
        if (isset($meta['discovered_fields']) && is_array($meta['discovered_fields'])) {
            foreach ($meta['discovered_fields'] as $field) {
                if (is_array($field) && isset($field['key'])) {
                    $discovered[] = $field;
                }
            }
        }

        return [
            'id' => (string) $document->id,
            'batch_id' => (string) $document->batch_id,
            'template_id' => $document->template_id ? (string) $document->template_id : null,
            'original_filename' => (string) $document->original_filename,
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'status' => (string) $document->status,
            'scan_engine' => $document->scan_engine,
            'mode' => (string) ($meta['mode'] ?? ($document->template_id ? 'template' : 'auto')),
            'page_count' => isset($meta['page_count']) ? (int) $meta['page_count'] : null,
            'field_values' => is_array($document->field_values) ? $document->field_values : [],
            'discovered_fields' => $discovered,
            'error_message' => $document->error_message,
            'purged_at' => optional($document->purged_at)?->toIso8601String(),
            'has_file' => $document->stored_path !== null && $document->purged_at === null,
            'created_at' => optional($document->created_at)?->toIso8601String(),
            'updated_at' => optional($document->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, null>
     */
    private function emptyFieldValues(DocExtractTemplate $template): array
    {
        $values = [];
        foreach (is_array($template->fields) ? $template->fields : [] as $field) {
            if (is_array($field) && isset($field['key'])) {
                $values[(string) $field['key']] = null;
            }
        }

        return $values;
    }
}
