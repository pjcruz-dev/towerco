<?php

declare(strict_types=1);

namespace App\Modules\Documents\Support;

/**
 * Parsed from E-Approval form metadata_json.controlledDocumentSync.
 */
final class ControlledDocumentSyncConfig
{
    /**
     * @param  array<string, string>  $fieldMap
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $fieldMap,
        public readonly string $attachmentField,
        public readonly bool $autoRevision,
        public readonly ?string $documentCodeField,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public static function parse(?array $metadata): ?self
    {
        if ($metadata === null) {
            return null;
        }

        $raw = $metadata['controlledDocumentSync'] ?? $metadata['controlled_document_sync'] ?? null;
        if (! is_array($raw)) {
            return null;
        }

        $enabled = (bool) ($raw['enabled'] ?? false);
        if (! $enabled) {
            return null;
        }

        $defaults = [
            'title' => 'title',
            'document_type' => 'document_type',
            'department' => 'department',
            'revision_number' => 'revision_number',
            'effective_date' => 'effective_date',
            'next_review_date' => 'next_review_date',
            'change_summary' => 'change_summary',
        ];

        $map = $defaults;
        $custom = $raw['fieldMap'] ?? $raw['field_map'] ?? null;
        if (is_array($custom)) {
            foreach ($custom as $key => $fieldName) {
                if (is_string($key) && is_string($fieldName) && trim($fieldName) !== '') {
                    $map[$key] = trim($fieldName);
                }
            }
        }

        $attachmentField = trim((string) ($raw['attachmentField'] ?? $raw['attachment_field'] ?? 'attachments'));
        if ($attachmentField === '') {
            $attachmentField = 'attachments';
        }

        $documentCodeField = trim((string) ($raw['documentCodeField'] ?? $raw['document_code_field'] ?? 'document_code'));
        if ($documentCodeField === '') {
            $documentCodeField = null;
        }

        $autoRevision = array_key_exists('autoRevision', $raw) || array_key_exists('auto_revision', $raw)
            ? (bool) ($raw['autoRevision'] ?? $raw['auto_revision'] ?? true)
            : true;

        return new self(true, $map, $attachmentField, $autoRevision, $documentCodeField);
    }

    public function revisionFieldName(): string
    {
        return $this->fieldMap['revision_number'] ?? 'revision_number';
    }
}
