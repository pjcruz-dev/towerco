<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Models\ControlledDocumentRevision;
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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ControlledDocumentSyncService
{
    public function __construct(
        private readonly ControlledDocumentStorageService $storage,
    ) {}

    public function syncApprovedSubmission(EApprovalSubmission $submission, ?TenantUser $actor = null): ?ControlledDocument
    {
        if ((string) $submission->status !== EApprovalSubmissionStatus::APPROVED) {
            return null;
        }

        if (ControlledDocumentRevision::query()->where('e_approval_submission_id', $submission->id)->exists()) {
            return ControlledDocument::query()
                ->whereHas('revisions', static fn ($q) => $q->where('e_approval_submission_id', $submission->id))
                ->first();
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

            if ($existing === null) {
                $existing = ControlledDocument::query()->create([
                    'id' => (string) Str::uuid(),
                    'document_code' => $documentCode,
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

            return $existing->fresh(['revisions']);
        });
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
            return $value;
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
                return $candidate;
            }
        }

        return null;
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
        $scoped = $submission->attachments
            ->first(static fn (EApprovalAttachment $a): bool => (string) $a->field_name === $fieldName);

        if ($scoped instanceof EApprovalAttachment) {
            return $scoped;
        }

        return $submission->attachments->first();
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
