<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
use App\Modules\Documents\Support\ControlledDocumentMasterLabel;
use App\Modules\Documents\Support\ControlledDocumentRevisionStatus;
use App\Modules\Documents\Support\ControlledDocumentStatus;
use App\Modules\Documents\Support\ControlledDocumentSyncConfig;
use App\Modules\EApproval\Models\EApprovalAttachment;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Support\EApprovalSubmissionStatus;
use App\Modules\Identity\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentSyncService
{
    public function __construct(
        private readonly ControlledDocumentStorageService $storage,
        private readonly ControlledDocumentDcfNumberService $dcfNumbers,
        private readonly ControlledDocumentMasterLabel $labels,
    ) {}

    public function syncApprovedSubmission(EApprovalSubmission $submission, ?TenantUser $actor = null): ?ControlledDocument
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            return null;
        }

        $linked = ControlledDocumentRevision::query()
            ->where('e_approval_submission_id', $submission->id)
            ->first();
        if ($linked instanceof ControlledDocumentRevision) {
            $linked->loadMissing('document');
            $existingDocument = $linked->document;
            if (
                $existingDocument instanceof ControlledDocument
                && $existingDocument->status === ControlledDocumentStatus::PENDING
                && $linked->status === ControlledDocumentRevisionStatus::PENDING
            ) {
                return $this->publishPending($existingDocument, $linked, $submission, $actor);
            }

            return $existingDocument;
        }

        $submission->loadMissing(['form', 'values.field', 'attachments']);

        $form = $submission->form;
        if (! $form instanceof EApprovalForm) {
            return null;
        }

        $config = $this->resolveConfig($form);
        if ($config === null) {
            return null;
        }

        $values = $this->valuesMap($submission);
        $documentCode = $this->resolveRegistryDocumentCode($config, $values, $submission);
        if ($documentCode === '') {
            return null;
        }

        return DB::connection('tenant')->transaction(function () use (
            $submission,
            $form,
            $config,
            $values,
            $documentCode,
            $actor,
        ): ControlledDocument {
            /** @var ControlledDocument|null $existing */
            $existing = ControlledDocument::query()
                ->where('document_code', $documentCode)
                ->lockForUpdate()
                ->first();

            $revisionFromField = $this->fieldValue($values, $config->fieldMap, 'revision_number');
            $revisionNumber = $this->parseRevisionNumber(
                $revisionFromField,
                $existing !== null ? ((int) $existing->current_revision + 1) : 0,
            );

            if ($existing !== null && $existing->revisions()->where('revision_number', $revisionNumber)->exists()) {
                throw ValidationException::withMessages([
                    'revision_number' => [__('Revision :rev already exists for document :code.', [
                        'rev' => (string) $revisionNumber,
                        'code' => $documentCode,
                    ])],
                ]);
            }

            $title = $this->fieldValue($values, $config->fieldMap, 'title') ?: $documentCode;
            $effectiveDate = $this->parseDate($this->fieldValue($values, $config->fieldMap, 'effective_date'));
            $nextReviewDate = $this->parseDate($this->fieldValue($values, $config->fieldMap, 'next_review_date'));
            $changeSummary = $this->fieldValue($values, $config->fieldMap, 'change_summary');

            $requestorId = (string) ($submission->requestor_id ?? '');
            $authorId = $requestorId !== '' ? $requestorId : ($actor !== null ? (string) $actor->id : null);
            $dcfNumber = $this->dcfNumbers->next();

            if ($existing === null) {
                $existing = ControlledDocument::query()->create([
                    'id' => (string) Str::uuid(),
                    'document_code' => $documentCode,
                    'dcf_number' => $dcfNumber,
                    'title' => $title,
                    'document_type' => $this->fieldValue($values, $config->fieldMap, 'document_type'),
                    'department' => $this->fieldValue($values, $config->fieldMap, 'department'),
                    'current_revision' => $revisionNumber,
                    'status' => ControlledDocumentStatus::PUBLISHED,
                    'effective_date' => $effectiveDate,
                    'next_review_date' => $nextReviewDate,
                    'e_approval_form_id' => $form->id,
                    // Registry ownership follows the requestor (DCF author), not the final approver.
                    'created_by_id' => $authorId,
                    'published_at' => now(),
                ]);
            } else {
                $existing->fill([
                    'title' => $title,
                    'dcf_number' => $dcfNumber,
                    'document_type' => $this->fieldValue($values, $config->fieldMap, 'document_type') ?: $existing->document_type,
                    'department' => $this->fieldValue($values, $config->fieldMap, 'department') ?: $existing->department,
                    'current_revision' => $revisionNumber,
                    'status' => ControlledDocumentStatus::PUBLISHED,
                    'effective_date' => $effectiveDate ?? $existing->effective_date,
                    'next_review_date' => $nextReviewDate ?? $existing->next_review_date,
                    'published_at' => now(),
                ]);
                $existing->save();

                $existing->revisions()
                    ->where('status', ControlledDocumentRevisionStatus::PUBLISHED)
                    ->where('revision_number', '<', $revisionNumber)
                    ->update(['status' => ControlledDocumentRevisionStatus::SUPERSEDED]);
            }

            $revision = ControlledDocumentRevision::query()->create([
                'id' => (string) Str::uuid(),
                'controlled_document_id' => $existing->id,
                'revision_number' => $revisionNumber,
                'dcf_number' => $dcfNumber,
                'change_summary' => $changeSummary,
                'e_approval_submission_id' => $submission->id,
                'status' => ControlledDocumentRevisionStatus::PUBLISHED,
                'effective_date' => $effectiveDate,
                'approved_by_id' => $actor?->id,
                'approved_at' => now(),
                'created_by_id' => $authorId,
            ]);

            $attachment = $this->primaryAttachment($submission, $config->attachmentField);
            if ($attachment instanceof EApprovalAttachment) {
                $copied = $this->storage->copyFromEApprovalPath(
                    $existing,
                    $revision,
                    (string) $attachment->file_path,
                    (string) $attachment->file_name,
                );
                $revision->fill($copied);
                $revision->save();
            }

            $this->stampPublishedDcf($submission, $dcfNumber);

            return $existing->fresh(['revisions']);
        });
    }

    public function openPending(EApprovalSubmission $submission, ?TenantUser $actor = null): ?ControlledDocument
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::PENDING) {
            return null;
        }

        $submission->loadMissing(['form', 'values.field']);
        $form = $submission->form;
        if (! $form instanceof EApprovalForm) {
            return null;
        }

        $config = $this->resolveConfig($form);
        if ($config === null) {
            return null;
        }

        $values = $this->valuesMap($submission);
        $documentCode = $this->resolveRegistryDocumentCode($config, $values, $submission);
        if ($documentCode === '') {
            return null;
        }

        if (! ControlledDocument::query()->where('document_code', $documentCode)->exists()
            && preg_match('/^(.+)-R\d+$/i', $documentCode, $matches) === 1) {
            $baseCode = $matches[1];
            if (! ControlledDocument::query()->where('document_code', $baseCode)->exists()) {
                return null;
            }
            $documentCode = $baseCode;
        }

        return DB::connection('tenant')->transaction(function () use ($submission, $form, $config, $values, $documentCode, $actor): ?ControlledDocument {
            /** @var ControlledDocument|null $existing */
            $existing = ControlledDocument::query()
                ->where('document_code', $documentCode)
                ->lockForUpdate()
                ->first();

            if ($existing instanceof ControlledDocument && $existing->status !== ControlledDocumentStatus::PENDING) {
                return $existing;
            }

            $revisionFromField = $this->fieldValue($values, $config->fieldMap, 'revision_number');
            $revisionNumber = $this->parseRevisionNumber(
                $revisionFromField,
                $existing !== null ? ((int) $existing->current_revision + 1) : 0,
            );
            $title = $this->fieldValue($values, $config->fieldMap, 'title') ?: $documentCode;
            $requestorId = (string) ($submission->requestor_id ?? '');
            $authorId = $requestorId !== '' ? $requestorId : ($actor !== null ? (string) $actor->id : null);

            if ($existing === null) {
                $existing = ControlledDocument::query()->create([
                    'id' => (string) Str::uuid(),
                    'document_code' => $documentCode,
                    'title' => $title,
                    'document_type' => $this->fieldValue($values, $config->fieldMap, 'document_type'),
                    'department' => $this->fieldValue($values, $config->fieldMap, 'department'),
                    'current_revision' => $revisionNumber,
                    'status' => ControlledDocumentStatus::PENDING,
                    'effective_date' => $this->parseDate($this->fieldValue($values, $config->fieldMap, 'effective_date')),
                    'next_review_date' => $this->parseDate($this->fieldValue($values, $config->fieldMap, 'next_review_date')),
                    'e_approval_form_id' => $form->id,
                    'created_by_id' => $authorId,
                ]);
            } else {
                $existing->fill([
                    'title' => $title,
                    'document_type' => $this->fieldValue($values, $config->fieldMap, 'document_type') ?: $existing->document_type,
                    'department' => $this->fieldValue($values, $config->fieldMap, 'department') ?: $existing->department,
                    'current_revision' => $revisionNumber,
                    'effective_date' => $this->parseDate($this->fieldValue($values, $config->fieldMap, 'effective_date')) ?? $existing->effective_date,
                    'next_review_date' => $this->parseDate($this->fieldValue($values, $config->fieldMap, 'next_review_date')) ?? $existing->next_review_date,
                ]);
                $existing->save();
            }

            $revision = $existing->revisions()->where('e_approval_submission_id', $submission->id)->first();
            if (! $revision instanceof ControlledDocumentRevision) {
                $revision = $existing->revisions()->where('revision_number', $revisionNumber)->first();
            }
            if ($revision instanceof ControlledDocumentRevision && $revision->status !== ControlledDocumentRevisionStatus::PENDING) {
                return $existing;
            }

            if (! $revision instanceof ControlledDocumentRevision) {
                ControlledDocumentRevision::query()->create([
                    'id' => (string) Str::uuid(),
                    'controlled_document_id' => $existing->id,
                    'revision_number' => $revisionNumber,
                    'change_summary' => $this->fieldValue($values, $config->fieldMap, 'change_summary'),
                    'e_approval_submission_id' => $submission->id,
                    'status' => ControlledDocumentRevisionStatus::PENDING,
                    'effective_date' => $this->parseDate($this->fieldValue($values, $config->fieldMap, 'effective_date')),
                    'created_by_id' => $authorId,
                ]);
            }

            $this->attachPendingFile($submission);

            return $existing->fresh(['revisions']);
        });
    }

    public function attachPendingFile(EApprovalSubmission $submission): void
    {
        $revision = ControlledDocumentRevision::query()
            ->where('e_approval_submission_id', $submission->id)
            ->first();
        if (! $revision instanceof ControlledDocumentRevision || $revision->status !== ControlledDocumentRevisionStatus::PENDING) {
            return;
        }
        if ($revision->stored_path !== null && $revision->stored_path !== '') {
            return;
        }

        $revision->loadMissing('document');
        $document = $revision->document;
        if (! $document instanceof ControlledDocument || $document->status !== ControlledDocumentStatus::PENDING) {
            return;
        }

        $submission->load(['form', 'attachments']);
        $form = $submission->form;
        $config = $form instanceof EApprovalForm ? $this->resolveConfig($form) : null;
        if ($config === null) {
            return;
        }

        $attachment = $this->primaryAttachment($submission, $config->attachmentField);
        if (! $attachment instanceof EApprovalAttachment) {
            return;
        }

        try {
            $copied = $this->storage->copyFromEApprovalPath(
                $document,
                $revision,
                (string) $attachment->file_path,
                (string) $attachment->file_name,
            );
            $revision->fill($copied);
            $revision->save();
        } catch (\Throwable $exception) {
            Log::warning('Controlled document pending file was not copied', [
                'submission_id' => (string) $submission->id,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    public function releasePending(EApprovalSubmission $submission): void
    {
        $revision = ControlledDocumentRevision::query()
            ->where('e_approval_submission_id', $submission->id)
            ->first();
        if (! $revision instanceof ControlledDocumentRevision || $revision->status !== ControlledDocumentRevisionStatus::PENDING) {
            return;
        }

        $revision->loadMissing('document');
        $document = $revision->document;
        if (! $document instanceof ControlledDocument || $document->status !== ControlledDocumentStatus::PENDING) {
            return;
        }

        $this->storage->deleteStoredFile($revision->stored_path);
        $document->delete();
    }

    private function publishPending(
        ControlledDocument $document,
        ControlledDocumentRevision $revision,
        EApprovalSubmission $submission,
        ?TenantUser $actor,
    ): ControlledDocument {
        $submission->loadMissing(['form', 'values.field', 'attachments']);
        $form = $submission->form;
        $config = $form instanceof EApprovalForm ? $this->resolveConfig($form) : null;
        $values = $this->valuesMap($submission);
        $fieldMap = $config?->fieldMap ?? [];

        $dcfNumber = trim((string) ($revision->dcf_number ?? ''));
        if ($dcfNumber === '') {
            $dcfNumber = $this->dcfNumbers->next();
        }

        $document->fill([
            'title' => $this->fieldValue($values, $fieldMap, 'title') ?: $document->title,
            'dcf_number' => $dcfNumber,
            'status' => ControlledDocumentStatus::PUBLISHED,
            'published_at' => now(),
            'effective_date' => $this->parseDate($this->fieldValue($values, $fieldMap, 'effective_date')) ?? $document->effective_date,
            'next_review_date' => $this->parseDate($this->fieldValue($values, $fieldMap, 'next_review_date')) ?? $document->next_review_date,
        ]);
        $document->save();

        $revision->fill([
            'dcf_number' => $dcfNumber,
            'status' => ControlledDocumentRevisionStatus::PUBLISHED,
            'change_summary' => $this->fieldValue($values, $fieldMap, 'change_summary') ?? $revision->change_summary,
            'approved_by_id' => $actor?->id,
            'approved_at' => now(),
        ]);
        $revision->save();

        if ($config !== null) {
            $attachment = $this->primaryAttachment($submission, $config->attachmentField);
            if ($attachment instanceof EApprovalAttachment && ($revision->stored_path === null || $revision->stored_path === '')) {
                $copied = $this->storage->copyFromEApprovalPath(
                    $document,
                    $revision,
                    (string) $attachment->file_path,
                    (string) $attachment->file_name,
                );
                $revision->fill($copied);
                $revision->save();
            }
        }

        $document->revisions()
            ->where('status', ControlledDocumentRevisionStatus::PUBLISHED)
            ->where('revision_number', '<', (int) $revision->revision_number)
            ->update(['status' => ControlledDocumentRevisionStatus::SUPERSEDED]);

        $this->stampPublishedDcf($submission, $dcfNumber);

        return $document->fresh(['revisions']);
    }

    /**
     * The request form keeps this field hidden. The number is written when the document is published.
     */
    private function stampPublishedDcf(EApprovalSubmission $submission, string $dcfNumber): void
    {
        $submission->loadMissing('form.fields');
        $form = $submission->form;
        if (! $form instanceof EApprovalForm) {
            return;
        }

        $field = $form->fields->first(
            static fn ($candidate): bool => $candidate instanceof EApprovalFormField && $candidate->name === 'dcf_number',
        );
        if (! $field instanceof EApprovalFormField) {
            return;
        }

        $row = EApprovalFormValue::query()
            ->where('submission_id', $submission->id)
            ->where('field_id', $field->id)
            ->first();

        if ($row === null) {
            EApprovalFormValue::query()->create([
                'id' => (string) Str::uuid(),
                'submission_id' => $submission->id,
                'field_id' => $field->id,
                'value' => $dcfNumber,
            ]);
        } elseif (trim((string) $row->value) === '') {
            $row->value = $dcfNumber;
            $row->save();
        }

        $this->ensureSnapshotHasDcfField($submission, $field);
    }

    private function ensureSnapshotHasDcfField(EApprovalSubmission $submission, EApprovalFormField $field): void
    {
        $raw = $submission->schema_snapshot_json;
        if (! is_string($raw) || trim($raw) === '') {
            return;
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return;
        }

        if (! is_array($decoded) || ! is_array($decoded['fields'] ?? null)) {
            return;
        }

        foreach ($decoded['fields'] as $existing) {
            if (is_array($existing) && ($existing['name'] ?? null) === 'dcf_number') {
                return;
            }
        }

        $decoded['fields'][] = [
            'id' => (string) $field->id,
            'type' => $field->type,
            'name' => $field->name,
            'label' => $field->label,
            'semantic_type' => $field->semantic_type,
            'validation' => $field->validation,
            'options' => $field->options,
        ];

        $submission->schema_snapshot_json = json_encode($decoded, JSON_THROW_ON_ERROR);
        $submission->save();
    }

    public function resolveConfig(EApprovalForm $form): ?ControlledDocumentSyncConfig
    {
        $meta = $form->metadata_json;
        if (is_string($meta)) {
            $decoded = json_decode($meta, true);
            $meta = is_array($decoded) ? $decoded : null;
        }

        return ControlledDocumentSyncConfig::parse(is_array($meta) ? $meta : null);
    }

    /**
     * @param  array<string, string>  $values
     */
    private function resolveRegistryDocumentCode(
        ControlledDocumentSyncConfig $config,
        array $values,
        EApprovalSubmission $submission,
    ): string {
        if ($config->documentCodeField !== null) {
            $fromField = trim((string) ($values[$config->documentCodeField] ?? ''));
            if ($fromField !== '') {
                return $fromField;
            }
        }

        return trim((string) $submission->document_no);
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $fieldMap
     */
    private function fieldValue(array $values, array $fieldMap, string $key): ?string
    {
        $fieldName = $fieldMap[$key] ?? $key;
        $value = trim((string) ($values[$fieldName] ?? ''));

        if ($value !== '') {
            return $this->registerLabel($key, $value);
        }

        $fallbacks = match ($key) {
            'revision_number' => ['revision_no', 'revision'],
            'next_review_date' => ['review_date'],
            'change_summary' => ['details', 'reason', 'purpose'],
            default => [],
        };

        foreach ($fallbacks as $fallback) {
            $candidate = trim((string) ($values[$fallback] ?? ''));
            if ($candidate !== '') {
                return $this->registerLabel($key, $candidate);
            }
        }

        return null;
    }

    private function registerLabel(string $key, string $value): string
    {
        $label = match ($key) {
            'document_type' => $this->labels->documentType($value),
            'department' => $this->labels->department($value),
            default => $value,
        };

        return $label ?? $value;
    }

    /**
     * @return array<string, string>
     */
    private function valuesMap(EApprovalSubmission $submission): array
    {
        $map = [];
        foreach ($submission->values as $value) {
            $name = $value->field?->name;
            if (is_string($name) && $name !== '') {
                $map[$name] = (string) ($value->value ?? '');
            }
        }

        return $map;
    }

    private function primaryAttachment(EApprovalSubmission $submission, string $fieldName): ?EApprovalAttachment
    {
        $attachments = $submission->attachments->sortBy(
            static fn (EApprovalAttachment $attachment): int => $attachment->created_at?->getTimestamp() ?? 0,
        );
        $scoped = $attachments
            ->first(static fn (EApprovalAttachment $attachment): bool => (string) $attachment->field_name === $fieldName);

        if ($scoped instanceof EApprovalAttachment) {
            return $scoped;
        }

        return $attachments->first();
    }

    private function parseRevisionNumber(?string $raw, int $fallback): int
    {
        if ($raw === null || trim($raw) === '') {
            return max(0, $fallback);
        }

        if (is_numeric($raw)) {
            return max(0, (int) $raw);
        }

        if (preg_match('/(\d+)/', $raw, $matches) === 1) {
            return max(0, (int) $matches[1]);
        }

        return max(0, $fallback);
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
