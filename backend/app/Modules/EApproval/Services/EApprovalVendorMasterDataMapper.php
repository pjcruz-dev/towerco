<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;

final class EApprovalVendorMasterDataMapper
{
    /** @var array<string, mixed> */
    private array $config;

    public function __construct(
        private readonly EApprovalVendorMasterDataDedupeService $dedupe,
    ) {
        $this->config = config('e_approval_vendor_master_data', []);
    }

    public function schemaVersion(): int
    {
        return (int) ($this->config['schema_version'] ?? 1);
    }

    /**
     * @return array<string, mixed>
     */
    public function setConfig(): array
    {
        $dedupe = is_array($this->config['dedupe'] ?? null) ? $this->config['dedupe'] : [];

        return [
            'schema_version' => $this->schemaVersion(),
            'source_form_family' => 'vendor_registration',
            'lookup_subtitle_paths' => $this->lookupSubtitlePaths(),
            'dedupe' => $dedupe,
        ];
    }

    /**
     * @return list<string>
     */
    public function valueFields(): array
    {
        $fields = $this->config['value_fields'] ?? [];

        return is_array($fields) ? array_values(array_filter($fields, is_string(...))) : [];
    }

    public function attachmentField(): string
    {
        return (string) ($this->config['attachment_field'] ?? 'compliance_documents');
    }

    /**
     * @return array<string, mixed>
     */
    public function mapSubmission(EApprovalSubmission $submission): array
    {
        $flat = $this->extractValueFields($submission);
        $companyName = trim((string) ($flat['company_name'] ?? ''));
        $taxId = trim((string) ($flat['tax_id'] ?? ''));
        $normalizedTaxId = $this->normalizeTaxId($taxId);
        $normalizedCompanyName = $this->dedupe->normalizeCompanyName($companyName);

        $payload = [
            'schema_version' => $this->schemaVersion(),
            'company_name' => $companyName,
            'company_name_normalized' => $normalizedCompanyName,
            'tax_id' => $taxId,
            'tax_id_normalized' => $normalizedTaxId,
            'vendor_category' => trim((string) ($flat['vendor_category'] ?? '')),
            'contact' => [
                'name' => trim((string) ($flat['contact_name'] ?? '')),
                'email' => trim((string) ($flat['contact_email'] ?? '')),
                'phone' => trim((string) ($flat['contact_phone'] ?? '')),
            ],
            'address' => [
                'registered' => trim((string) ($flat['registered_address'] ?? '')),
            ],
            'services_offered' => trim((string) ($flat['services_offered'] ?? '')),
            'banking' => [
                'bank_name' => trim((string) ($flat['bank_name'] ?? '')),
                'account_no' => trim((string) ($flat['bank_account_no'] ?? '')),
            ],
            'compliance_documents' => $this->extractComplianceDocuments($submission),
            'source' => [
                'submission_id' => (string) $submission->id,
                'document_no' => (string) $submission->document_no,
                'approved_at' => now()->toIso8601String(),
            ],
        ];

        return $this->withLegacyFlatAliases($payload);
    }

    /**
     * @return array<string, mixed>
     */
    public function withDedupeMetadata(array $payload, string $matchedBy, string $matchedRowId): array
    {
        $payload['dedupe'] = [
            'matched_by' => $matchedBy,
            'matched_row_id' => $matchedRowId,
            'synced_at' => now()->toIso8601String(),
        ];

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function lookupSubtitle(array $data): ?string
    {
        $parts = [];

        foreach ($this->lookupSubtitlePaths() as $path) {
            $value = trim((string) ($this->readPath($data, $path) ?? ''));
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(' · ', $parts);
    }

    public function normalizeTaxId(string $taxId): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim($taxId)) ?? '');
    }

    /**
     * @return array<string, string>
     */
    private function extractValueFields(EApprovalSubmission $submission): array
    {
        $allowed = array_flip($this->valueFields());
        $values = [];

        foreach ($submission->values as $value) {
            $fieldName = (string) ($value->field?->name ?? '');
            if (! isset($allowed[$fieldName])) {
                continue;
            }

            $raw = trim((string) ($value->value ?? ''));
            if ($raw !== '') {
                $values[$fieldName] = $raw;
            }
        }

        return $values;
    }

    /**
     * @return list<array{id: string, file_name: string, field_name: string}>
     */
    private function extractComplianceDocuments(EApprovalSubmission $submission): array
    {
        $fieldName = $this->attachmentField();

        return $submission->attachments
            ->filter(static fn ($attachment) => (string) $attachment->field_name === $fieldName)
            ->map(static fn ($attachment) => [
                'id' => (string) $attachment->id,
                'file_name' => (string) $attachment->file_name,
                'field_name' => (string) $attachment->field_name,
            ])
            ->values()
            ->all();
    }

    /**
     * Preserve top-level keys used by existing consumers and tests.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withLegacyFlatAliases(array $payload): array
    {
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
        $address = is_array($payload['address'] ?? null) ? $payload['address'] : [];
        $banking = is_array($payload['banking'] ?? null) ? $payload['banking'] : [];
        $source = is_array($payload['source'] ?? null) ? $payload['source'] : [];

        return array_merge($payload, [
            'contact_name' => (string) ($contact['name'] ?? ''),
            'contact_email' => (string) ($contact['email'] ?? ''),
            'contact_phone' => (string) ($contact['phone'] ?? ''),
            'registered_address' => (string) ($address['registered'] ?? ''),
            'bank_name' => (string) ($banking['bank_name'] ?? ''),
            'bank_account_no' => (string) ($banking['account_no'] ?? ''),
            'source_submission_id' => (string) ($source['submission_id'] ?? ''),
            'source_document_no' => (string) ($source['document_no'] ?? ''),
            'approved_at' => (string) ($source['approved_at'] ?? ''),
        ]);
    }

    /**
     * @return list<string>
     */
    private function lookupSubtitlePaths(): array
    {
        $paths = $this->config['lookup_subtitle_paths'] ?? [];

        return is_array($paths) ? array_values(array_filter($paths, is_string(...))) : [];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function readPath(array $data, string $path): mixed
    {
        $current = $data;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

}
