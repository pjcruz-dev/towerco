<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Services;

use App\Core\Support\ModuleListSearchDsl;
use App\Modules\DocExtract\Jobs\ProcessDocExtractDocumentJob;
use App\Modules\DocExtract\Models\DocExtractBatch;
use App\Modules\DocExtract\Models\DocExtractDocument;
use App\Modules\DocExtract\Models\DocExtractTemplate;
use App\Modules\DocExtract\Support\DocExtractBatchStatus;
use App\Modules\DocExtract\Support\DocExtractDocumentStatus;
use App\Modules\DocExtract\Support\DocExtractTemplateStatus;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class DocExtractBatchService
{
    public function __construct(
        private readonly DocExtractFileStorageService $storage,
        private readonly DocExtractTemplateService $templates,
        private readonly DocExtractAuditLogger $audit,
        private readonly DocExtractScanClient $scanClient,
    ) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 20,
        ?string $status = null,
        ?string $search = null,
        ?string $sort = null,
    ): array {
        $base = DocExtractBatch::query();

        if ($search !== null && trim($search) !== '') {
            $this->applyBatchSearch($base, $search);
        }

        $statusCounts = [
            'all' => (clone $base)->count(),
            'processing' => (clone $base)->whereIn('status', [
                DocExtractBatchStatus::PROCESSING,
                DocExtractBatchStatus::PENDING,
            ])->count(),
            'ready' => (clone $base)->where('status', DocExtractBatchStatus::READY)->count(),
            'failed' => (clone $base)->where('status', DocExtractBatchStatus::FAILED)->count(),
        ];

        $query = DocExtractBatch::query()->with([
            'template:id,name',
            'documents:id,batch_id,original_filename,scan_meta,stored_path,created_at',
        ]);

        if ($status !== null && $status !== '' && $status !== 'all') {
            if ($status === 'processing') {
                $query->whereIn('status', [
                    DocExtractBatchStatus::PROCESSING,
                    DocExtractBatchStatus::PENDING,
                ]);
            } else {
                $query->where('status', $status);
            }
        }

        if ($search !== null && trim($search) !== '') {
            $this->applyBatchSearch($query, $search);
        }

        [$sortField, $sortDir] = $this->parseListSort($sort);
        if ($sortField === 'template_name') {
            $query
                ->leftJoin('doc_extract_templates', 'doc_extract_templates.id', '=', 'doc_extract_batches.template_id')
                ->select('doc_extract_batches.*')
                ->orderBy('doc_extract_templates.name', $sortDir)
                ->orderByDesc('doc_extract_batches.created_at');
        } elseif ($sortField === 'primary_filename') {
            // Sort by earliest document filename on the batch (correlated subquery).
            $query->orderByRaw(
                '(select min(original_filename) from doc_extract_documents where doc_extract_documents.batch_id = doc_extract_batches.id) '.$sortDir
            )->orderByDesc('created_at');
        } else {
            $query->orderBy($sortField, $sortDir);
            if ($sortField !== 'created_at') {
                $query->orderByDesc('created_at');
            }
        }

        $paginator = $query->paginate(max(1, min(100, $perPage)), ['*'], 'page', max(1, $page));

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
                'status_counts' => $statusCounts,
            ],
        ];
    }

    /**
     * @param  list<string>|null  $ids
     */
    public function countListRows(
        ?string $status = null,
        ?string $search = null,
        ?array $ids = null,
    ): int {
        if ($ids !== null && $ids !== []) {
            $ids = array_values(array_unique(array_slice(array_map('strval', $ids), 0, 500)));

            return DocExtractBatch::query()->whereIn('id', $ids)->count();
        }

        $result = $this->paginate(1, 1, $status, $search, null);

        return (int) ($result['meta']['total'] ?? 0);
    }

    /**
     * @param  list<string>|null  $ids
     * @return list<array<string, string>>
     */
    public function exportListRows(
        ?string $status = null,
        ?string $search = null,
        ?string $sort = null,
        int $limit = 5000,
        ?array $ids = null,
    ): array {
        if ($ids !== null && $ids !== []) {
            $ids = array_values(array_unique(array_slice(array_map('strval', $ids), 0, 500)));
            $batches = DocExtractBatch::query()
                ->with([
                    'template:id,name',
                    'documents:id,batch_id,original_filename,scan_meta,stored_path,created_at',
                ])
                ->whereIn('id', $ids)
                ->orderByDesc('created_at')
                ->limit(500)
                ->get();

            $rows = [];
            foreach ($batches as $batch) {
                /** @var DocExtractBatch $batch */
                $this->reconcileStaleProcessingStatus($batch);
                $row = $this->asListRow($batch);
                $rows[] = [
                    'id' => (string) ($row['id'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'primary_filename' => (string) ($row['primary_filename'] ?? ''),
                    'file_count' => (string) ($row['file_count'] ?? '0'),
                    'template_name' => (string) ($row['template_name'] ?? 'Auto-detect'),
                    'mode' => (string) ($row['mode'] ?? ''),
                    'status' => (string) ($row['status'] ?? ''),
                    'document_count' => (string) ($row['document_count'] ?? '0'),
                    'ready_count' => (string) ($row['ready_count'] ?? '0'),
                    'failed_count' => (string) ($row['failed_count'] ?? '0'),
                    'message' => (string) ($row['message'] ?? ''),
                ];
            }

            return $rows;
        }

        $result = $this->paginate(1, max(1, min(5000, $limit)), $status, $search, $sort);
        $rows = [];
        foreach ($result['data'] as $row) {
            $rows[] = [
                'id' => (string) ($row['id'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'primary_filename' => (string) ($row['primary_filename'] ?? ''),
                'file_count' => (string) ($row['file_count'] ?? '0'),
                'template_name' => (string) ($row['template_name'] ?? 'Auto-detect'),
                'mode' => (string) ($row['mode'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'document_count' => (string) ($row['document_count'] ?? '0'),
                'ready_count' => (string) ($row['ready_count'] ?? '0'),
                'failed_count' => (string) ($row['failed_count'] ?? '0'),
                'message' => (string) ($row['message'] ?? ''),
            ];
        }

        return $rows;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function parseListSort(?string $sort): array
    {
        $allowed = ['created_at', 'status', 'template_name', 'document_count', 'updated_at', 'primary_filename'];
        $field = 'created_at';
        $dir = 'desc';
        if ($sort !== null && str_contains($sort, ':')) {
            [$rawField, $rawDir] = array_pad(explode(':', $sort, 2), 2, 'desc');
            if (in_array($rawField, $allowed, true)) {
                $field = $rawField;
            }
            if (in_array(strtolower((string) $rawDir), ['asc', 'desc'], true)) {
                $dir = strtolower((string) $rawDir);
            }
        }

        return [$field, $dir];
    }

    /**
     * Apply list search across batch id, message, template name, and source filenames.
     * Supports DSL tokens: status:ready, status!=failed, filename~invoice, template~PO.
     */
    private function applyBatchSearch(Builder $query, string $search): void
    {
        $parsed = ModuleListSearchDsl::parse($search);
        $rebuildParts = [$parsed['residual']];

        foreach ($parsed['clauses'] as $clause) {
            if ($clause['key'] === 'status') {
                $this->applyBatchStatusClause($query, $clause['op'], $clause['value']);
                continue;
            }

            $rebuildParts[] = $clause['key'].match ($clause['op']) {
                ModuleListSearchDsl::OP_NE => '!=',
                ModuleListSearchDsl::OP_CONTAINS => '~',
                default => ':',
            }.(str_contains($clause['value'], ' ') ? '"'.$clause['value'].'"' : $clause['value']);
        }

        $withoutStatus = trim(preg_replace('/\s+/', ' ', implode(' ', array_filter($rebuildParts))) ?? '');
        if ($withoutStatus === '') {
            return;
        }

        ModuleListSearchDsl::apply(
            $query,
            $withoutStatus,
            [
                'filename' => [
                    'relation' => 'documents',
                    'relation_column' => 'original_filename',
                    'type' => 'string',
                ],
                'file' => [
                    'relation' => 'documents',
                    'relation_column' => 'original_filename',
                    'type' => 'string',
                ],
                'template' => [
                    'relation' => 'template',
                    'relation_column' => 'name',
                    'type' => 'string',
                ],
                'message' => [
                    'column' => 'message',
                    'type' => 'string',
                ],
                'created' => [
                    'column' => 'created_at',
                    'type' => 'exact',
                ],
                'created_at' => [
                    'column' => 'created_at',
                    'type' => 'exact',
                ],
            ],
            static function (Builder $innerQuery, string $residual): void {
                $term = '%'.mb_strtolower(trim($residual)).'%';
                $innerQuery->where(function ($inner) use ($term): void {
                    $inner
                        ->whereRaw('LOWER(id) like ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(message, \'\')) like ?', [$term])
                        ->orWhereHas('template', function ($template) use ($term): void {
                            $template->whereRaw('LOWER(name) like ?', [$term]);
                        })
                        ->orWhereHas('documents', function ($documents) use ($term): void {
                            $documents
                                ->whereRaw('LOWER(original_filename) like ?', [$term])
                                ->orWhereRaw('LOWER(COALESCE(JSON_UNQUOTE(JSON_EXTRACT(scan_meta, \'$.source_filename\')), \'\')) like ?', [$term]);
                        });
                });
            },
        );
    }

    private function applyBatchStatusClause(Builder $query, string $op, string $value): void
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '' || $normalized === 'all') {
            return;
        }

        $processingBucket = [
            DocExtractBatchStatus::PENDING,
            DocExtractBatchStatus::PROCESSING,
        ];

        if ($op === ModuleListSearchDsl::OP_NE) {
            if ($normalized === 'processing' || $normalized === 'pending') {
                $query->whereNotIn('status', $processingBucket);
            } else {
                $query->where(function (Builder $inner) use ($normalized): void {
                    $inner->where('status', '!=', $normalized)->orWhereNull('status');
                });
            }

            return;
        }

        if ($op === ModuleListSearchDsl::OP_CONTAINS) {
            $like = '%'.addcslashes($normalized, '%_\\').'%';
            $query->where('status', 'like', $like);

            return;
        }

        if ($normalized === 'processing' || $normalized === 'pending') {
            $query->whereIn('status', $processingBucket);

            return;
        }

        $query->where('status', $normalized);
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
     * @param  list<array{file_index?: mixed, pages?: mixed, label?: mixed}>|null  $records
     *        When provided, each item becomes one extract document with exact page membership.
     *        When null, falls back to one-document-per-file (or per-page if $splitPages).
     * @param  array<int, int>  $filePageCounts  Optional page counts from Consolidate preview (file index → pages).
     */
    public function create(
        ?string $templateId,
        array $files,
        TenantUser $actor,
        bool $splitPages = false,
        ?array $records = null,
        array $filePageCounts = [],
    ): DocExtractBatch {
        $maxFiles = max(1, (int) config('doc_extract.max_files_per_batch', 25));
        $maxPagesPerFile = max(1, (int) config('doc_extract.max_pages_per_file', 50));
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

        /** @var list<array{meta: array{original_filename: string, stored_path: string, mime_type: string, size_bytes: int}, source_pages: list<int>|null, display_name: string}> $planned */
        $planned = [];
        /** @var array<string, true> $storedPaths */
        $storedPaths = [];
        try {
            /** @var list<array{original_filename: string, stored_path: string, mime_type: string, size_bytes: int}> $fileMetas */
            $fileMetas = [];
            foreach ($files as $file) {
                $meta = $this->storage->store($batchId, $file);
                $storedPaths[(string) $meta['stored_path']] = true;
                $fileMetas[] = $meta;
            }

            if ($records !== null) {
                $planned = $this->planFromRecords($fileMetas, $records, $maxFiles, $maxPagesPerFile, $filePageCounts);
            } else {
                $planned = $this->planFromFiles($fileMetas, $splitPages, $maxFiles, $maxPagesPerFile);
            }
        } catch (\Throwable $exception) {
            foreach (array_keys($storedPaths) as $path) {
                $this->storage->delete($path);
            }
            throw $exception;
        }

        $batch = DocExtractBatch::query()->create([
            'id' => $batchId,
            'template_id' => $template?->id,
            // Mark processing before dispatch: with QUEUE_CONNECTION=sync jobs finish
            // inside dispatch() and refresh counters to ready/failed; never overwrite after.
            'status' => DocExtractBatchStatus::PROCESSING,
            'document_count' => count($planned),
            'ready_count' => 0,
            'failed_count' => 0,
            'created_by_id' => $actor->id,
        ]);

        $tenantId = (string) (tenant('id') ?? '');

        foreach ($planned as $item) {
            $meta = $item['meta'];
            $sourcePages = $item['source_pages'];
            $scanMeta = null;
            if ($sourcePages !== null && $sourcePages !== []) {
                $scanMeta = [
                    'source_pages' => array_values($sourcePages),
                    'source_page' => $sourcePages[0],
                    'consolidated' => true,
                    'source_filename' => $meta['original_filename'],
                ];
            }

            $document = DocExtractDocument::query()->create([
                'id' => (string) Str::uuid(),
                'batch_id' => $batch->id,
                'template_id' => $template?->id,
                'original_filename' => $item['display_name'],
                'stored_path' => $meta['stored_path'],
                'mime_type' => $meta['mime_type'],
                'size_bytes' => $meta['size_bytes'],
                'status' => DocExtractDocumentStatus::PENDING,
                'field_values' => $template !== null ? $this->emptyFieldValues($template) : [],
                'scan_meta' => $scanMeta,
            ]);

            // Push OCR to Redis (never sync) so create can return before scans run.
            $queueConnection = (string) config('doc_extract.queue_connection', 'redis');
            if ($queueConnection === '' || $queueConnection === 'sync') {
                $queueConnection = 'redis';
            }
            ProcessDocExtractDocumentJob::dispatch($tenantId, (string) $document->id)
                ->onConnection($queueConnection);
        }

        // Documents are still pending here; mark batch processing without waiting for OCR.
        $batch->refresh();
        $this->refreshBatchCounters($batch);

        $this->audit->record(
            action: 'batch.created',
            summary: __('DocExtract batch created with :count document(s).', ['count' => count($planned)]),
            entityType: 'batch',
            entityId: (string) $batch->id,
            entityLabel: $template?->name ?? 'Auto-detect',
            actor: $actor,
            changes: [
                'document_count' => ['from' => null, 'to' => count($planned)],
                'template_id' => ['from' => null, 'to' => $template?->id],
                'mode' => ['from' => null, 'to' => $template !== null ? 'template' : 'auto'],
                'split_pages' => ['from' => null, 'to' => $splitPages],
                'consolidated' => ['from' => null, 'to' => $records !== null],
            ],
        );

        // Avoid reloading all documents — list row is enough for the create response.
        $batch->unsetRelation('documents');
        $batch->loadMissing('template');

        return $batch;
    }

    /**
     * Build page thumbnails / counts for the Consolidate step (no OCR, no batch created).
     *
     * @param  list<UploadedFile>  $files
     * @return list<array{index: int, filename: string, mime_type: string|null, size_bytes: int, page_count: int, pages: list<array{page: int, thumbnail: string|null, text_chars: int, likely_blank: bool}>}>
     */
    public function previewFiles(array $files): array
    {
        $maxFiles = max(1, (int) config('doc_extract.max_files_per_batch', 25));
        $maxPagesPerFile = max(1, (int) config('doc_extract.max_pages_per_file', 50));
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

        $out = [];
        foreach (array_values($files) as $index => $file) {
            /** @var UploadedFile $file */
            $filename = (string) ($file->getClientOriginalName() ?: 'document');
            $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
            $bytes = (string) $file->getContent();
            if ($bytes === '') {
                throw ValidationException::withMessages([
                    'files' => [__('Unable to read :name.', ['name' => $filename])],
                ]);
            }

            try {
                $preview = $this->scanClient->preview($filename, $mime, $bytes);
            } catch (\Throwable $exception) {
                throw ValidationException::withMessages([
                    'files' => [__('Unable to preview :name. :detail', [
                        'name' => $filename,
                        'detail' => $exception->getMessage(),
                    ])],
                ]);
            }

            if ($preview['page_count'] > $maxPagesPerFile) {
                throw ValidationException::withMessages([
                    'files' => [__(
                        ':name has :pages pages. Max pages per file is :max.',
                        [
                            'name' => $filename,
                            'pages' => $preview['page_count'],
                            'max' => $maxPagesPerFile,
                        ],
                    )],
                ]);
            }

            $out[] = [
                'index' => $index,
                'filename' => $filename,
                'mime_type' => $mime,
                'size_bytes' => (int) $file->getSize(),
                'page_count' => $preview['page_count'],
                'pages' => $preview['pages'],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{original_filename: string, stored_path: string, mime_type: string, size_bytes: int}>  $fileMetas
     * @param  list<array{file_index?: mixed, pages?: mixed, label?: mixed}>  $records
     * @param  array<int, int>  $filePageCounts
     * @return list<array{meta: array{original_filename: string, stored_path: string, mime_type: string, size_bytes: int}, source_pages: list<int>|null, display_name: string}>
     */
    private function planFromRecords(
        array $fileMetas,
        array $records,
        int $maxFiles,
        int $maxPagesPerFile,
        array $filePageCounts = [],
    ): array {
        if ($records === []) {
            throw ValidationException::withMessages([
                'records' => [__('Add at least one record in Consolidate before extracting.')],
            ]);
        }
        if (count($records) > $maxFiles) {
            throw ValidationException::withMessages([
                'records' => [__(
                    'Consolidate produced :count records. Max per batch is :max.',
                    ['count' => count($records), 'max' => $maxFiles],
                )],
            ]);
        }

        /** @var array<int, int> $pageCounts */
        $pageCounts = [];
        foreach ($fileMetas as $index => $meta) {
            $mime = strtolower((string) ($meta['mime_type'] ?? ''));
            $isPdf = $mime === 'application/pdf'
                || str_ends_with(strtolower((string) $meta['original_filename']), '.pdf');
            if (! $isPdf) {
                $pageCounts[$index] = 1;
                continue;
            }

            // Prefer Consolidate preview counts — avoids re-loading large PDFs into PHP memory.
            if (isset($filePageCounts[$index]) && (int) $filePageCounts[$index] > 0) {
                $pageCounts[$index] = (int) $filePageCounts[$index];
            } else {
                $bytes = $this->storage->readBytes((string) $meta['stored_path']);
                try {
                    $pageCounts[$index] = $this->scanClient->pageCount(
                        (string) $meta['original_filename'],
                        (string) ($meta['mime_type'] ?? 'application/pdf'),
                        $bytes,
                    );
                } catch (\Throwable $exception) {
                    unset($bytes);
                    throw ValidationException::withMessages([
                        'files' => [__('Unable to read page count for :name. :detail', [
                            'name' => $meta['original_filename'],
                            'detail' => $exception->getMessage(),
                        ])],
                    ]);
                }
                unset($bytes);
            }

            if ($pageCounts[$index] > $maxPagesPerFile) {
                throw ValidationException::withMessages([
                    'files' => [__(
                        ':name has :pages pages. Max pages per file is :max.',
                        [
                            'name' => $meta['original_filename'],
                            'pages' => $pageCounts[$index],
                            'max' => $maxPagesPerFile,
                        ],
                    )],
                ]);
            }
        }

        /** @var array<string, true> $claimed */
        $claimed = [];
        $planned = [];

        foreach ($records as $recordIndex => $record) {
            if (! is_array($record)) {
                throw ValidationException::withMessages([
                    'records' => [__('Record :n is invalid.', ['n' => $recordIndex + 1])],
                ]);
            }
            $fileIndex = (int) ($record['file_index'] ?? -1);
            if (! isset($fileMetas[$fileIndex])) {
                throw ValidationException::withMessages([
                    'records' => [__('Record :n references an unknown file.', ['n' => $recordIndex + 1])],
                ]);
            }
            $meta = $fileMetas[$fileIndex];
            $maxPage = $pageCounts[$fileIndex] ?? 1;

            $rawPages = $record['pages'] ?? [];
            if (! is_array($rawPages) || $rawPages === []) {
                throw ValidationException::withMessages([
                    'records' => [__('Record :n must include at least one page.', ['n' => $recordIndex + 1])],
                ]);
            }

            $pages = [];
            foreach ($rawPages as $pageRaw) {
                $page = (int) $pageRaw;
                if ($page < 1 || $page > $maxPage) {
                    throw ValidationException::withMessages([
                        'records' => [__(
                            'Record :n includes page :page, but :file only has :max page(s).',
                            [
                                'n' => $recordIndex + 1,
                                'page' => $page,
                                'file' => $meta['original_filename'],
                                'max' => $maxPage,
                            ],
                        )],
                    ]);
                }
                $claimKey = $fileIndex.':'.$page;
                if (isset($claimed[$claimKey])) {
                    throw ValidationException::withMessages([
                        'records' => [__(
                            'Page :page of :file is assigned to more than one record. Each page can belong to only one record.',
                            ['page' => $page, 'file' => $meta['original_filename']],
                        )],
                    ]);
                }
                $claimed[$claimKey] = true;
                $pages[$page] = $page;
            }
            $pageList = array_values($pages);
            sort($pageList);

            $label = trim((string) ($record['label'] ?? ''));
            if ($label === '') {
                $label = $this->defaultRecordLabel((string) $meta['original_filename'], $pageList);
            }

            $planned[] = [
                'meta' => $meta,
                'source_pages' => $pageList,
                'display_name' => mb_substr($label, 0, 255),
            ];
        }

        return $planned;
    }

    /**
     * @param  list<array{original_filename: string, stored_path: string, mime_type: string, size_bytes: int}>  $fileMetas
     * @return list<array{meta: array{original_filename: string, stored_path: string, mime_type: string, size_bytes: int}, source_pages: list<int>|null, display_name: string}>
     */
    private function planFromFiles(array $fileMetas, bool $splitPages, int $maxFiles, int $maxPagesPerFile): array
    {
        $planned = [];
        foreach ($fileMetas as $meta) {
            $mime = strtolower((string) ($meta['mime_type'] ?? ''));
            $isPdf = $mime === 'application/pdf'
                || str_ends_with(strtolower((string) $meta['original_filename']), '.pdf');

            if ($splitPages && $isPdf) {
                $bytes = $this->storage->readBytes((string) $meta['stored_path']);
                try {
                    $pageCount = $this->scanClient->pageCount(
                        (string) $meta['original_filename'],
                        (string) ($meta['mime_type'] ?? 'application/pdf'),
                        $bytes,
                    );
                } catch (\Throwable $exception) {
                    throw ValidationException::withMessages([
                        'files' => [__('Unable to read page count for :name. :detail', [
                            'name' => $meta['original_filename'],
                            'detail' => $exception->getMessage(),
                        ])],
                    ]);
                }

                if ($pageCount > $maxPagesPerFile) {
                    throw ValidationException::withMessages([
                        'split_pages' => [__(
                            ':name has :pages pages. Max pages per file when splitting is :max.',
                            [
                                'name' => $meta['original_filename'],
                                'pages' => $pageCount,
                                'max' => $maxPagesPerFile,
                            ],
                        )],
                    ]);
                }

                for ($page = 1; $page <= $pageCount; $page++) {
                    $planned[] = [
                        'meta' => $meta,
                        'source_pages' => [$page],
                        'display_name' => $this->defaultRecordLabel((string) $meta['original_filename'], [$page]),
                    ];
                }
            } else {
                $planned[] = [
                    'meta' => $meta,
                    'source_pages' => null,
                    'display_name' => (string) $meta['original_filename'],
                ];
            }
        }

        if (count($planned) > $maxFiles) {
            throw ValidationException::withMessages([
                'split_pages' => [__(
                    'This upload would create :count records (after page split). Max per batch is :max. Reduce pages, upload fewer files, or turn off page split.',
                    ['count' => count($planned), 'max' => $maxFiles],
                )],
            ]);
        }

        return $planned;
    }

    /**
     * @param  list<int>  $pages
     */
    private function defaultRecordLabel(string $filename, array $pages): string
    {
        if ($pages === []) {
            return $filename;
        }
        if (count($pages) === 1) {
            return sprintf('%s (page %d)', $filename, $pages[0]);
        }

        return sprintf('%s (pages %s)', $filename, $this->formatPageRange($pages));
    }

    /**
     * @param  list<int>  $pages
     */
    private function formatPageRange(array $pages): string
    {
        $sorted = array_values($pages);
        sort($sorted);
        $parts = [];
        $start = $sorted[0];
        $prev = $sorted[0];
        for ($i = 1; $i < count($sorted); $i++) {
            $current = $sorted[$i];
            if ($current === $prev + 1) {
                $prev = $current;
                continue;
            }
            $parts[] = $start === $prev ? (string) $start : $start.'–'.$prev;
            $start = $current;
            $prev = $current;
        }
        $parts[] = $start === $prev ? (string) $start : $start.'–'.$prev;

        return implode(', ', $parts);
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
     * Replace the curated column list for a batch (remove / reorder / edit fields).
     *
     * @param  list<array{key?: mixed, label?: mixed, type?: mixed, hint?: mixed}>  $fields
     * @return list<array{key: string, label: string, type: string, hint: string|null}>
     */
    public function updateFieldSchema(DocExtractBatch $batch, array $fields, ?TenantUser $actor = null): array
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

        $previousCount = count($this->effectiveFields($batch));
        $batch->field_schema = $normalized;
        $batch->save();
        $batch->loadMissing('template');

        $this->audit->record(
            action: 'batch.fields_updated',
            summary: __('DocExtract columns updated (:count).', ['count' => count($normalized)]),
            entityType: 'batch',
            entityId: (string) $batch->id,
            entityLabel: $batch->template?->name ?? 'Auto-detect',
            actor: $actor,
            changes: [
                'field_count' => ['from' => $previousCount, 'to' => count($normalized)],
            ],
        );

        return $normalized;
    }

    /**
     * Drop one discovered column from the batch review grid.
     *
     * @return list<array{key: string, label: string, type: string, hint: string|null}>
     */
    public function removeField(DocExtractBatch $batch, string $fieldKey, ?TenantUser $actor = null): array
    {
        $current = $this->effectiveFields($batch);
        $remaining = array_values(array_filter(
            $current,
            static fn (array $field): bool => (string) $field['key'] !== $fieldKey,
        ));

        return $this->updateFieldSchema($batch, $remaining, $actor);
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

        $becameReady = false;
        if ($pending > 0) {
            $batch->status = DocExtractBatchStatus::PROCESSING;
        } elseif ($failed > 0 && $ready === 0) {
            $batch->status = DocExtractBatchStatus::FAILED;
            $batch->message = 'All documents failed to scan.';
        } else {
            $becameReady = (string) $batch->status !== DocExtractBatchStatus::READY;
            $batch->status = DocExtractBatchStatus::READY;
            $batch->message = null;
        }

        $batch->save();

        if ($becameReady) {
            $this->reconcileAutoFieldValues($batch);
        }
    }

    /**
     * Re-dispatch OCR for documents left pending/scanning after queue loss (OOM, worker restart).
     *
     * @return array{requeued: int, batches: int}
     */
    public function requeueStuckDocuments(?string $batchId = null, string $tenantId = ''): array
    {
        $query = DocExtractDocument::query()
            ->whereIn('status', [DocExtractDocumentStatus::PENDING, DocExtractDocumentStatus::SCANNING])
            ->whereNull('purged_at')
            ->whereNotNull('stored_path');

        if ($batchId !== null && $batchId !== '') {
            $query->where('batch_id', $batchId);
        }

        $documents = $query->orderBy('created_at')->get();
        if ($documents->isEmpty()) {
            return ['requeued' => 0, 'batches' => 0];
        }

        if ($tenantId === '') {
            $tenantId = (string) (tenant('id') ?? '');
        }

        $queueConnection = (string) config('doc_extract.queue_connection', 'redis');
        if ($queueConnection === '' || $queueConnection === 'sync') {
            $queueConnection = 'redis';
        }

        $batchIds = [];
        $requeued = 0;
        foreach ($documents as $document) {
            $document->status = DocExtractDocumentStatus::PENDING;
            $document->error_message = null;
            $document->save();

            ProcessDocExtractDocumentJob::dispatch($tenantId, (string) $document->id)
                ->onConnection($queueConnection);
            $batchIds[(string) $document->batch_id] = true;
            $requeued++;
        }

        foreach (array_keys($batchIds) as $id) {
            $batch = DocExtractBatch::query()->find($id);
            if ($batch !== null) {
                $this->refreshBatchCounters($batch);
            }
        }

        return ['requeued' => $requeued, 'batches' => count($batchIds)];
    }

    /**
     * Auto mode discovers fields per document. After the batch is ready, remap each
     * document's OCR text onto the union schema so shared columns fill across pages.
     */
    public function reconcileAutoFieldValues(DocExtractBatch $batch): void
    {
        $batch->loadMissing(['documents', 'template']);

        if ($batch->template_id !== null) {
            return;
        }
        if (is_array($batch->field_schema) && $batch->field_schema !== []) {
            return;
        }

        $fields = $this->effectiveFields($batch);
        if ($fields === []) {
            return;
        }

        foreach ($batch->documents as $document) {
            if ((string) $document->status !== DocExtractDocumentStatus::READY) {
                continue;
            }
            $text = trim((string) ($document->extracted_text ?? ''));
            if ($text === '') {
                continue;
            }

            $meta = is_array($document->scan_meta) ? $document->scan_meta : [];
            if (($meta['schema_reconciled'] ?? false) === true) {
                continue;
            }

            try {
                $mapped = $this->scanClient->mapText($text, $fields);
            } catch (\Throwable $exception) {
                Log::warning('DocExtract schema reconcile failed', [
                    'document_id' => (string) $document->id,
                    'message' => $exception->getMessage(),
                ]);
                continue;
            }

            $existing = is_array($document->field_values) ? $document->field_values : [];
            $merged = $existing;
            foreach ($fields as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }
                $current = $merged[$key] ?? null;
                $incoming = $mapped[$key] ?? null;
                if (($current === null || $current === '') && $incoming !== null && $incoming !== '') {
                    $merged[$key] = $incoming;
                } elseif (! array_key_exists($key, $merged)) {
                    $merged[$key] = $incoming;
                }
            }

            $document->field_values = $merged;
            $document->scan_meta = array_merge($meta, [
                'schema_reconciled' => true,
                'schema_field_count' => count($fields),
            ]);
            $document->save();
        }
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
        $fileSummary = $this->batchFileSummary($batch);

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
            'primary_filename' => $fileSummary['primary_filename'],
            'file_count' => $fileSummary['file_count'],
            'created_at' => optional($batch->created_at)?->toIso8601String(),
            'updated_at' => optional($batch->updated_at)?->toIso8601String(),
        ];
    }

    /**
     * First uploaded/source file name; when multiple distinct files, UI appends " +++".
     *
     * @return array{primary_filename: string|null, file_count: int}
     */
    private function batchFileSummary(DocExtractBatch $batch): array
    {
        if (! $batch->relationLoaded('documents')) {
            $batch->load(['documents:id,batch_id,original_filename,scan_meta,stored_path,created_at']);
        }

        $seen = [];
        $ordered = [];
        foreach ($batch->documents->sortBy('created_at') as $document) {
            /** @var DocExtractDocument $document */
            $name = $this->documentSourceFilename($document);
            $key = mb_strtolower($name);
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $ordered[] = $name;
        }

        return [
            'primary_filename' => $ordered[0] ?? null,
            'file_count' => count($ordered),
        ];
    }

    private function documentSourceFilename(DocExtractDocument $document): string
    {
        $meta = is_array($document->scan_meta) ? $document->scan_meta : [];
        if (isset($meta['source_filename']) && is_string($meta['source_filename']) && trim($meta['source_filename']) !== '') {
            return trim($meta['source_filename']);
        }

        $name = trim((string) $document->original_filename);
        $stripped = preg_replace('/ \((?:page|pages) .+\)\s*$/u', '', $name);

        return is_string($stripped) && $stripped !== '' ? $stripped : $name;
    }

    /**
     * @return array<string, mixed>
     */
    public function asDetail(DocExtractBatch $batch): array
    {
        $batch->loadMissing(['template', 'documents']);

        // Heal older auto batches that finished before schema reconcile existed.
        if ((string) $batch->status === DocExtractBatchStatus::READY) {
            $this->reconcileAutoFieldValues($batch);
            $batch->load('documents');
        }

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
            'source_page' => isset($meta['source_page']) ? (int) $meta['source_page'] : null,
            'source_pages' => isset($meta['source_pages']) && is_array($meta['source_pages'])
                ? array_values(array_map('intval', $meta['source_pages']))
                : (isset($meta['source_page']) ? [(int) $meta['source_page']] : null),
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
