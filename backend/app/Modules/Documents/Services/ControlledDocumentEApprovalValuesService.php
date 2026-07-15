<?php

declare(strict_types=1);

namespace App\Modules\Documents\Services;

use App\Modules\Documents\Models\ControlledDocument;
use App\Modules\Documents\Support\ControlledDocumentSyncConfig;
use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Services\EApprovalDocumentSequenceService;

final class ControlledDocumentEApprovalValuesService
{
    public function __construct(
        private readonly EApprovalDocumentSequenceService $sequenceService,
    ) {}

    /**
     * Apply auto-revision for draft saves without allocating a new document number.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function prepareForDraft(EApprovalForm $form, array $values): array
    {
        $config = $this->resolveConfig($form);
        if ($config === null) {
            return $values;
        }

        $documentNo = $config->documentCodeField !== null
            ? trim((string) ($values[$config->documentCodeField] ?? ''))
            : '';

        return $this->applyAutoRevision($config, $values, $documentNo);
    }

    /**
     * Prepare form values for final submission and return the canonical document number.
     *
     * - New controlled document: $allocateDocumentNo() is called to mint a fresh
     *   sequence number which is also stamped into the form values as the registry code.
     *   document_no  = the new registry code (e.g. ATC-P-SCM-001).
     *
     * - Revision of an existing document: $allocateDocumentNo() is NOT called (no
     *   slot wasted). Instead, a dedicated revision-submission counter is used to
     *   produce a unique, traceable number (e.g. ATC-P-SCM-001-R001, -R002 …).
     *   document_no  = {registryCode}-R{seq:3}
     *
     * @param  array<string, mixed>  $values
     * @param  \Closure(): string    $allocateDocumentNo
     * @return array{values: array<string, mixed>, document_no: string}
     */
    public function prepareForSubmit(EApprovalForm $form, array $values, \Closure $allocateDocumentNo): array
    {
        $config = $this->resolveConfig($form);
        if ($config === null) {
            return [
                'values' => $values,
                'document_no' => $allocateDocumentNo(),
            ];
        }

        $registryCode = $config->documentCodeField !== null
            ? trim((string) ($values[$config->documentCodeField] ?? ''))
            : '';

        if ($registryCode === '' && $config->documentCodeField !== null) {
            // New controlled document: mint a registry code and stamp it into values.
            $registryCode = $allocateDocumentNo();
            $values[$config->documentCodeField] = $registryCode;

            $values = $this->applyAutoRevision($config, $values, $registryCode);

            return [
                'values' => $values,
                'document_no' => $registryCode,
            ];
        }

        // Revision of an existing document: allocate a revision-submission counter
        // so the document_no is unique yet clearly linked to the document being revised.
        $values = $this->applyAutoRevision($config, $values, $registryCode);

        return [
            'values' => $values,
            'document_no' => $this->sequenceService->nextRevisionNumber($registryCode),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lookupByDocumentCode(string $documentCode): ?array
    {
        $code = trim($documentCode);
        if ($code === '') {
            return null;
        }

        $document = ControlledDocument::query()
            ->where('document_code', $code)
            ->first();

        if ($document === null) {
            return null;
        }

        return [
            'exists' => true,
            'document_code' => $document->document_code,
            'title' => $document->title,
            'document_type' => $document->document_type,
            'department' => $document->department,
            'current_revision' => (int) $document->current_revision,
            'next_revision' => (int) $document->current_revision + 1,
            'effective_date' => $document->effective_date?->toDateString(),
            'next_review_date' => $document->next_review_date?->toDateString(),
            'status' => $document->status,
        ];
    }

    public function nextRevisionForDocumentCode(string $documentCode): int
    {
        $code = trim($documentCode);
        if ($code === '') {
            return 0;
        }

        $document = ControlledDocument::query()
            ->where('document_code', $code)
            ->first();

        return $document !== null ? ((int) $document->current_revision + 1) : 0;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function resolveDocumentNumber(ControlledDocumentSyncConfig $config, array $values, string $generatedDocumentNo): string
    {
        if ($config->documentCodeField === null) {
            return $generatedDocumentNo;
        }

        $explicit = trim((string) ($values[$config->documentCodeField] ?? ''));
        if ($explicit !== '') {
            return $explicit;
        }

        return $generatedDocumentNo;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public function applyAutoRevision(ControlledDocumentSyncConfig $config, array $values, string $documentCode): array
    {
        if (! $config->autoRevision) {
            return $values;
        }

        $fieldName = $config->revisionFieldName();
        $current = trim((string) ($values[$fieldName] ?? ''));
        if ($current !== '') {
            return $values;
        }

        $values[$fieldName] = (string) $this->nextRevisionForDocumentCode($documentCode);

        return $values;
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
}
