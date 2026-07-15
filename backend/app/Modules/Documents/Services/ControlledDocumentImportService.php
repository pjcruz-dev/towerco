<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\ControlledDocumentRevisionStatus;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentImportService
{
    public function __construct(
        private readonly ControlledDocumentStorageService $storage,
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

        $revisionNumber = $this->parseRevisionNumber($data['revision_number'] ?? null, 0);

        return DB::connection('tenant')->transaction(function () use ($data, $actor, $code, $title, $revisionNumber): bool {
            /** @var ControlledDocument|null $document */
            $document = ControlledDocument::query()
                ->where('document_code', $code)
                ->lockForUpdate()
                ->first();

            if ($document !== null && $document->revisions()->where('revision_number', $revisionNumber)->exists()) {
                return false;
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

            ControlledDocumentRevision::query()->create([
                'id' => (string) Str::uuid(),
                'controlled_document_id' => $document->id,
                'revision_number' => $revisionNumber,
                'change_summary' => $this->nullableString($data['change_summary'] ?? null),
                'status' => ControlledDocumentRevisionStatus::PUBLISHED,
                'effective_date' => $effectiveDate,
                'approved_at' => now(),
                'approved_by_id' => $actor->id,
                'created_by_id' => $actor->id,
            ]);

            if ($revisionNumber < (int) $document->current_revision) {
                return true;
            }

            $document->revisions()
                ->where('revision_number', '<', $revisionNumber)
                ->where('status', ControlledDocumentRevisionStatus::PUBLISHED)
                ->update(['status' => ControlledDocumentRevisionStatus::SUPERSEDED]);

            return true;
        });
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
