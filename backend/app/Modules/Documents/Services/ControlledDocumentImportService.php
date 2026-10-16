<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Core\Support\ExcelWorkbookReader;
use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\ControlledDocumentImportArchive;
use App\Modules\Documents\Support\ControlledDocumentImportFields;
use App\Modules\Documents\Support\ControlledDocumentImportPathMatcher;
use App\Modules\Documents\Support\ControlledDocumentRevisionStatus;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentImportService
{
    public const MAX_ROWS = 2000;

    public function __construct(
        private readonly ControlledDocumentStorageService $storage,
        private readonly ControlledDocumentImportPathMatcher $paths,
        private readonly ControlledDocumentImportArchive $archive,
        private readonly TenantActivityLogger $activity,
        private readonly ControlledDocumentPendingImportService $pending,
        private readonly ExcelWorkbookReader $workbooks,
    ) {}

    /**
     * @return array{processed: int, skipped: int, errors: list<array{row: int, message: string}>}
     */
    public function importCsv(UploadedFile $file, TenantUser $actor): array
    {
        $handle = fopen($file->getRealPath() ?: '', 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => [__('Could not read the CSV file.')],
            ]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => [__('CSV file is empty.')],
            ]);
        }

        $columns = $this->normalizeHeader($header);
        $required = ['document_code', 'title'];
        foreach ($required as $column) {
            if (! in_array($column, $columns, true)) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => [__('CSV must include columns: document_code, title.')],
                ]);
            }
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($this->isBlankRow($row)) {
                continue;
            }

            $data = $this->rowToAssoc($columns, $row);

            try {
                $created = $this->importRow($data, $actor);
                if ($created) {
                    $processed++;
                } else {
                    $skipped++;
                }
            } catch (ValidationException $e) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => (string) collect($e->errors())->flatten()->first(),
                ];
            }
        }

        fclose($handle);

        return compact('processed', 'skipped', 'errors');
    }

    public function attachFileToRevision(
        ControlledDocument $document,
        ControlledDocumentRevision $revision,
        UploadedFile $file,
        TenantUser $actor,
    ): ControlledDocumentRevision {
        if ($revision->stored_path !== null && $revision->stored_path !== '') {
            throw ValidationException::withMessages([
                'file' => [__('This revision already has a file. Upload a new revision instead.')],
            ]);
        }

        $meta = $this->storage->storeUploadedFile($document, $revision, $file);
        $revision->fill($meta);
        $revision->created_by_id = $revision->created_by_id ?? $actor->id;
        $revision->save();

        return $revision->fresh();
    }

    public function templateCsv(): string
    {
        $headers = array_map(
            static fn (array $field): string => $field['name'],
            ControlledDocumentImportFields::all(),
        );

        return "\xEF\xBB\xBF".implode(',', $headers)."\n";
    }

    /**
     * @return array{headers: list<string>, suggested_map: array<string, string>, fields: list<array{name: string, label: string, required: bool}>, row_count: int}
     */
    public function analyze(UploadedFile $file): array
    {
        $parsed = $this->readTable($file);

        return [
            'headers' => $parsed['headers'],
            'suggested_map' => ControlledDocumentImportFields::suggestMap($parsed['headers']),
            'fields' => ControlledDocumentImportFields::all(),
            'row_count' => count($parsed['rows']),
        ];
    }

    /**
     * @param  array<string, string>  $columnMap
     * @param  list<string>  $availablePaths
     * @param  array<string, UploadedFile>  $filesByPath
     * @return array<string, mixed>
     */
    public function importMapped(
        UploadedFile $csv,
        array $columnMap,
        TenantUser $actor,
        array $availablePaths,
        array $filesByPath,
        bool $write,
    ): array {
        $parsed = $this->readTable($csv);
        $fieldIndex = $this->fieldIndex($parsed['headers'], $columnMap);
        $filesByKey = $this->indexFiles($filesByPath);

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $filesAttached = 0;
        $errors = [];
        $rows = [];

        foreach ($parsed['rows'] as $csvRow) {
            $data = $this->mapRow($fieldIndex, $csvRow['cells']);
            $match = $this->paths->match($data['folder_path'] ?? null, $availablePaths);
            $file = null;
            if ($match['status'] === 'matched' && is_string($match['path'])) {
                $file = $filesByKey[$this->paths->normalize($match['path'])] ?? null;
            }

            $problem = $this->pending->problem($data) ?? $this->rowProblem($data, $match['status']);
            if ($problem !== null) {
                $errors[] = ['row' => $csvRow['row'], 'message' => $problem];
                $rows[] = $this->rowResult($csvRow['row'], $data, 'error', $match['status'], $match['path'], $problem);

                continue;
            }

            if (! $write) {
                $outcome = $this->classifyExisting($data, $match['status']);
                if ($outcome === 'skipped') {
                    $skipped++;
                } elseif ($outcome === 'updated') {
                    $updated++;
                } elseif ($outcome === 'created') {
                    $created++;
                }
                $rows[] = $this->rowResult($csvRow['row'], $data, $outcome, $match['status'], $match['path'], null);

                continue;
            }

            try {
                $outcome = $this->pending->isPending($data)
                    ? $this->pending->open($data, $actor, $file)
                    : $this->writeRow($data, $actor, $file);
            } catch (ValidationException $exception) {
                $message = (string) collect($exception->errors())->flatten()->first();
                $errors[] = ['row' => $csvRow['row'], 'message' => $message];
                $rows[] = $this->rowResult($csvRow['row'], $data, 'error', $match['status'], $match['path'], $message);

                continue;
            }

            if ($outcome === 'created') {
                $created++;
            } elseif ($outcome === 'updated') {
                $updated++;
            } elseif ($outcome === 'skipped') {
                $skipped++;
            }

            if ($file instanceof UploadedFile && ($outcome === 'created' || $outcome === 'updated' || $outcome === 'attached')) {
                $filesAttached++;
            }

            $rows[] = $this->rowResult($csvRow['row'], $data, $outcome, $match['status'], $match['path'], null);
        }

        if ($write && ($created > 0 || $updated > 0 || $filesAttached > 0)) {
            $this->activity->record(
                module: 'documents',
                action: 'controlled_document.imported',
                summary: 'Imported controlled documents',
                entityType: 'controlled_document',
                actor: $actor,
                metadata: [
                    'created' => $created,
                    'updated' => $updated,
                    'skipped' => $skipped,
                    'files_attached' => $filesAttached,
                    'errors' => count($errors),
                ],
            );
        }

        return [
            'written' => $write,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'files_attached' => $filesAttached,
            'errors' => $errors,
            'rows' => $rows,
        ];
    }

    /**
     * @param  list<string>  $availablePaths
     * @param  array<string, UploadedFile>  $uploadedFiles
     * @return list<string>
     */
    public function availablePaths(?UploadedFile $archive, array $availablePaths, array $uploadedFiles): array
    {
        if ($archive instanceof UploadedFile) {
            $realPath = $archive->getRealPath();
            if ($realPath === false) {
                throw ValidationException::withMessages([
                    'archive' => [__('Could not read the zip file.')],
                ]);
            }

            return $this->archive->listPaths($realPath);
        }

        $paths = $availablePaths;
        foreach ($uploadedFiles as $path => $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = (string) $path;
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  list<string>  $entryNames
     * @return array<string, UploadedFile>
     */
    public function filesFromArchive(UploadedFile $archive, array $entryNames): array
    {
        $realPath = $archive->getRealPath();
        if ($realPath === false) {
            throw ValidationException::withMessages([
                'archive' => [__('Could not read the zip file.')],
            ]);
        }

        return $this->archive->extract($realPath, $entryNames);
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function importRow(array $data, TenantUser $actor): bool
    {
        $code = trim((string) ($data['document_code'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));

        if ($code === '' || $title === '') {
            throw ValidationException::withMessages([
                'document_code' => [__('document_code and title are required.')],
            ]);
        }

        $outcome = $this->pending->isPending($data)
            ? $this->pending->open($data, $actor, null)
            : $this->writeRow($data, $actor, null);

        return $outcome !== 'skipped';
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function writeRow(array $data, TenantUser $actor, ?UploadedFile $file): string
    {
        $code = trim((string) ($data['document_code'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        $revisionNumber = $this->parseRevisionNumber($data['revision_number'] ?? null, 0);

        return DB::connection('tenant')->transaction(function () use ($data, $actor, $file, $code, $title, $revisionNumber): string {
            /** @var ControlledDocument|null $document */
            $document = ControlledDocument::query()
                ->where('document_code', $code)
                ->lockForUpdate()
                ->first();

            $currentRevision = $this->currentRevision($document);
            $storeOnCurrent = $this->fileBelongsOnCurrent($document, $currentRevision, $revisionNumber, $file);

            $existingRevision = $document?->revisions()->where('revision_number', $revisionNumber)->first();
            if ($existingRevision instanceof ControlledDocumentRevision) {
                $target = $storeOnCurrent ? $currentRevision : $existingRevision;
                $targetHasFile = $target instanceof ControlledDocumentRevision
                    && is_string($target->stored_path)
                    && $target->stored_path !== '';
                if (! $document instanceof ControlledDocument || ! $target instanceof ControlledDocumentRevision || ! $file instanceof UploadedFile || $targetHasFile) {
                    if ($document instanceof ControlledDocument) {
                        $this->supersedeRevisionsBelowCurrent($document);
                    }

                    return 'skipped';
                }

                $this->storeRevisionFile($document, $target, $file, $actor);
                $this->supersedeRevisionsBelowCurrent($document);

                return 'attached';
            }

            $effectiveDate = $this->parseDate($data['effective_date'] ?? null);
            $nextReviewDate = $this->parseDate($data['next_review_date'] ?? null);

            if ($document === null) {
                $document = ControlledDocument::query()->create([
                    'id' => (string) Str::uuid(),
                    'document_code' => $code,
                    'title' => $title,
                    'document_type' => $this->nullableString($data['document_type'] ?? null),
                    'department' => $this->nullableString($data['department'] ?? null),
                    'current_revision' => $revisionNumber,
                    'status' => ControlledDocumentStatus::PUBLISHED,
                    'effective_date' => $effectiveDate,
                    'next_review_date' => $nextReviewDate,
                    'created_by_id' => $actor->id,
                    'published_at' => now(),
                ]);
            } else {
                $document->fill([
                    'title' => $title,
                    'document_type' => $this->nullableString($data['document_type'] ?? null) ?? $document->document_type,
                    'department' => $this->nullableString($data['department'] ?? null) ?? $document->department,
                    'current_revision' => max((int) $document->current_revision, $revisionNumber),
                    'status' => ControlledDocumentStatus::PUBLISHED,
                    'effective_date' => $effectiveDate ?? $document->effective_date,
                    'next_review_date' => $nextReviewDate ?? $document->next_review_date,
                    'published_at' => now(),
                ]);
                $document->save();
            }

            $belowCurrent = $revisionNumber < (int) $document->current_revision;
            $revision = ControlledDocumentRevision::query()->create([
                'id' => (string) Str::uuid(),
                'controlled_document_id' => $document->id,
                'revision_number' => $revisionNumber,
                'change_summary' => $this->nullableString($data['change_summary'] ?? null),
                'status' => $belowCurrent
                    ? ControlledDocumentRevisionStatus::SUPERSEDED
                    : ControlledDocumentRevisionStatus::PUBLISHED,
                'effective_date' => $effectiveDate,
                'approved_at' => now(),
                'approved_by_id' => $actor->id,
                'created_by_id' => $actor->id,
            ]);

            if ($file instanceof UploadedFile) {
                $target = $storeOnCurrent && $currentRevision instanceof ControlledDocumentRevision
                    ? $currentRevision
                    : $revision;
                if ($target->stored_path === null || $target->stored_path === '') {
                    $this->storeRevisionFile($document, $target, $file, $actor);
                }
            }

            $outcome = $document->wasRecentlyCreated ? 'created' : 'updated';
            $this->supersedeRevisionsBelowCurrent($document);

            return $outcome;
        });
    }

    private function currentRevision(?ControlledDocument $document): ?ControlledDocumentRevision
    {
        if (! $document instanceof ControlledDocument) {
            return null;
        }

        $revision = $document->revisions()
            ->where('revision_number', (int) $document->current_revision)
            ->first();

        return $revision instanceof ControlledDocumentRevision ? $revision : null;
    }

    private function fileBelongsOnCurrent(
        ?ControlledDocument $document,
        ?ControlledDocumentRevision $currentRevision,
        int $revisionNumber,
        ?UploadedFile $file,
    ): bool {
        if (! $document instanceof ControlledDocument || ! $currentRevision instanceof ControlledDocumentRevision || ! $file instanceof UploadedFile) {
            return false;
        }

        if ($revisionNumber >= (int) $document->current_revision) {
            return false;
        }

        return $currentRevision->stored_path === null || $currentRevision->stored_path === '';
    }

    private function supersedeRevisionsBelowCurrent(ControlledDocument $document): void
    {
        $document->revisions()
            ->where('revision_number', '<', (int) $document->current_revision)
            ->where('status', ControlledDocumentRevisionStatus::PUBLISHED)
            ->update(['status' => ControlledDocumentRevisionStatus::SUPERSEDED]);
    }

    private function storeRevisionFile(
        ControlledDocument $document,
        ControlledDocumentRevision $revision,
        UploadedFile $file,
        TenantUser $actor,
    ): void {
        $meta = $this->storage->storeUploadedFile($document, $revision, $file);
        $revision->fill($meta);
        $revision->created_by_id = $revision->created_by_id ?? $actor->id;
        $revision->save();
    }

    /**
     * @return array{headers: list<string>, rows: list<array{row: int, cells: list<string|null>}>}
     */
    private function readTable(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension === 'xlsx') {
            return $this->readXlsx($file);
        }

        return $this->readCsv($file);
    }

    /**
     * @return array{headers: list<string>, rows: list<array{row: int, cells: list<string|null>}>}
     */
    private function readXlsx(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $binary = $path !== false ? file_get_contents($path) : false;
        if (! is_string($binary) || $binary === '') {
            throw ValidationException::withMessages([
                'file' => [__('Could not read the spreadsheet.')],
            ]);
        }

        try {
            $grid = $this->workbooks->readFirstSheet($binary);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'file' => [__('Could not read the spreadsheet.')],
            ]);
        }

        if ($grid === []) {
            throw ValidationException::withMessages([
                'file' => [__('The spreadsheet is empty.')],
            ]);
        }

        $headers = [];
        foreach ($grid[0] as $index => $column) {
            $label = trim((string) $column);
            if ($index === 0) {
                $label = preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label;
            }
            $headers[] = $label;
        }

        $rows = [];
        foreach (array_slice($grid, 1) as $offset => $line) {
            if ($this->isBlankRow($line)) {
                continue;
            }
            $rows[] = ['row' => $offset + 2, 'cells' => $line];
            if (count($rows) > self::MAX_ROWS) {
                throw ValidationException::withMessages([
                    'file' => [__('The spreadsheet has too many rows.')],
                ]);
            }
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath() ?: '', 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => [__('Could not read the CSV file.')],
            ]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => [__('CSV file is empty.')],
            ]);
        }

        $headers = [];
        foreach ($header as $index => $column) {
            $label = trim((string) $column);
            if ($index === 0) {
                $label = preg_replace('/^\xEF\xBB\xBF/', '', $label) ?? $label;
            }
            $headers[] = $label;
        }

        $rows = [];
        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($this->isBlankRow($row)) {
                continue;
            }
            $rows[] = ['row' => $rowNumber, 'cells' => $row];
            if (count($rows) > self::MAX_ROWS) {
                fclose($handle);

                throw ValidationException::withMessages([
                    'file' => [__('The CSV has too many rows.')],
                ]);
            }
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, string>  $columnMap
     * @return array<int, string>
     */
    private function fieldIndex(array $headers, array $columnMap): array
    {
        $index = [];
        $seen = [];
        $allowed = ControlledDocumentImportFields::names();

        foreach ($headers as $position => $header) {
            $field = trim((string) ($columnMap[$header] ?? ControlledDocumentImportFields::IGNORE));
            if ($field === '' || $field === ControlledDocumentImportFields::IGNORE) {
                continue;
            }
            if (! in_array($field, $allowed, true)) {
                throw ValidationException::withMessages([
                    'column_map' => [__('Unknown register field: :field', ['field' => $field])],
                ]);
            }
            if (isset($seen[$field])) {
                throw ValidationException::withMessages([
                    'column_map' => [__('Map each register field only once.')],
                ]);
            }
            $seen[$field] = true;
            $index[$position] = $field;
        }

        foreach (['document_code', 'title'] as $required) {
            if (! in_array($required, $index, true)) {
                throw ValidationException::withMessages([
                    'column_map' => [__('Map document code and title before importing.')],
                ]);
            }
        }

        return $index;
    }

    /**
     * @param  array<int, string>  $fieldIndex
     * @param  list<string|null>  $cells
     * @return array<string, string|null>
     */
    private function mapRow(array $fieldIndex, array $cells): array
    {
        $data = [];
        foreach ($fieldIndex as $position => $field) {
            $data[$field] = isset($cells[$position]) ? trim((string) $cells[$position]) : null;
        }

        return $data;
    }

    /**
     * @param  array<string, UploadedFile>  $filesByPath
     * @return array<string, UploadedFile>
     */
    private function indexFiles(array $filesByPath): array
    {
        $indexed = [];
        foreach ($filesByPath as $path => $file) {
            if ($file instanceof UploadedFile) {
                $indexed[$this->paths->normalize((string) $path)] = $file;
            }
        }

        return $indexed;
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function rowProblem(array $data, string $fileStatus): ?string
    {
        $code = trim((string) ($data['document_code'] ?? ''));
        $title = trim((string) ($data['title'] ?? ''));
        if ($code === '' || $title === '') {
            return __('document_code and title are required.');
        }

        $status = strtolower(trim((string) ($data['status'] ?? '')));
        if ($status !== '' && ! in_array($status, ['pending', 'published'], true)) {
            return __('status must be pending or published.');
        }

        return match ($fileStatus) {
            'missing' => __('No file in the folder matches this path.'),
            'ambiguous' => __('More than one file matches this path.'),
            'invalid_type' => __('This file type is not allowed.'),
            default => null,
        };
    }

    /**
     * @param  array<string, string|null>  $data
     */
    private function classifyExisting(array $data, string $fileStatus): string
    {
        $code = trim((string) ($data['document_code'] ?? ''));
        $revisionNumber = $this->parseRevisionNumber($data['revision_number'] ?? null, 0);
        $document = ControlledDocument::query()->where('document_code', $code)->first();
        if ($this->pending->isPending($data)) {
            if (! $document instanceof ControlledDocument || $document->status === ControlledDocumentStatus::PUBLISHED) {
                return $document instanceof ControlledDocument ? 'updated' : 'created';
            }

            return 'skipped';
        }
        if ($document === null) {
            return 'created';
        }

        if ($revisionNumber < (int) $document->current_revision && $fileStatus === 'matched') {
            $currentRevision = $document->revisions()->where('revision_number', (int) $document->current_revision)->first();
            $currentHasFile = $currentRevision instanceof ControlledDocumentRevision
                && is_string($currentRevision->stored_path)
                && $currentRevision->stored_path !== '';
            if (! $currentHasFile) {
                return 'attached';
            }
        }

        $revision = $document->revisions()->where('revision_number', $revisionNumber)->first();
        if ($revision instanceof ControlledDocumentRevision) {
            $hasFile = is_string($revision->stored_path) && $revision->stored_path !== '';
            if (! $hasFile && $fileStatus === 'matched') {
                return 'attached';
            }

            return 'skipped';
        }

        return 'updated';
    }

    /**
     * @param  array<string, string|null>  $data
     * @return array{row: int, document_code: string, outcome: string, file_status: string, matched_path: string|null, message: string|null}
     */
    private function rowResult(int $row, array $data, string $outcome, string $fileStatus, ?string $matchedPath, ?string $message): array
    {
        return [
            'row' => $row,
            'document_code' => trim((string) ($data['document_code'] ?? '')),
            'outcome' => $outcome,
            'file_status' => $fileStatus,
            'matched_path' => $matchedPath,
            'message' => $message,
        ];
    }

    /**
     * @param  list<string|null>  $header
     * @return list<string>
     */
    private function normalizeHeader(array $header): array
    {
        return array_map(static function (?string $column): string {
            $normalized = strtolower(trim((string) $column));
            $normalized = str_replace([' ', '-'], '_', $normalized);

            return $normalized;
        }, $header);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<string|null>  $row
     * @return array<string, string|null>
     */
    private function rowToAssoc(array $columns, array $row): array
    {
        $assoc = [];
        foreach ($columns as $index => $column) {
            if ($column === '') {
                continue;
            }
            $assoc[$column] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $assoc;
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function nullableString(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function parseRevisionNumber(?string $raw, int $default): int
    {
        if ($raw === null || trim($raw) === '') {
            return $default;
        }

        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        if (preg_match('/(\d+)/', $raw, $matches) === 1) {
            return max(0, (int) $matches[1]);
        }

        return $default;
    }

    private function parseDate(?string $raw): ?Carbon
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
