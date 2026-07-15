<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\EApproval\Services\EApprovalVendorMasterDataMapper;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementVendor;
use App\Modules\ProcurementOne\Models\ProcurementVendorDocument;
use App\Modules\ProcurementOne\Support\ProcurementVendorAccreditationStatus;
use Illuminate\Support\Str;

final class ProcurementVendorSyncService
{
    public function __construct(
        private readonly EApprovalVendorMasterDataMapper $mapper,
        private readonly ProcurementVendorAccreditationService $accreditation,
    ) {}

    public function syncFromApprovedRegistration(
        EApprovalSubmission $submission,
        EApprovalMasterDataRow $masterDataRow,
        ?TenantUser $actor = null,
    ): ProcurementVendor {
        $data = is_array($masterDataRow->data_json) ? $masterDataRow->data_json : [];
        $vendor = $this->upsertFromMasterDataRow($masterDataRow, $data, (string) $submission->id);

        $this->syncComplianceDocuments($vendor, $data);
        $this->accreditation->accreditFromRegistration($vendor, $submission, $actor);

        return $vendor->refresh();
    }

    public function syncFromMasterDataRow(EApprovalMasterDataRow $row): ProcurementVendor
    {
        $data = is_array($row->data_json) ? $row->data_json : [];
        $vendor = $this->upsertFromMasterDataRow($row, $data, (string) ($data['source_submission_id'] ?? ''));

        $this->syncComplianceDocuments($vendor, $data);

        return $vendor->refresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function upsertFromMasterDataRow(
        EApprovalMasterDataRow $row,
        array $data,
        string $sourceSubmissionId,
    ): ProcurementVendor {
        $companyName = trim((string) ($data['company_name'] ?? $row->label));
        $taxId = trim((string) ($data['tax_id'] ?? ''));
        $vendorCode = trim((string) ($row->code ?? ''));
        if ($vendorCode === '') {
            $vendorCode = $this->mapper->normalizeTaxId($taxId);
        }

        $payload = [
            'master_data_row_id' => (string) $row->id,
            'vendor_code' => $vendorCode,
            'company_name' => $companyName,
            'tax_id' => $taxId,
            'category' => trim((string) ($data['vendor_category'] ?? '')) ?: null,
            'schema_version' => (int) ($data['schema_version'] ?? $this->mapper->schemaVersion()),
            'contact_json' => is_array($data['contact'] ?? null) ? $data['contact'] : [
                'name' => (string) ($data['contact_name'] ?? ''),
                'email' => (string) ($data['contact_email'] ?? ''),
                'phone' => (string) ($data['contact_phone'] ?? ''),
            ],
            'banking_json' => is_array($data['banking'] ?? null) ? $data['banking'] : [
                'bank_name' => (string) ($data['bank_name'] ?? ''),
                'account_no' => (string) ($data['bank_account_no'] ?? ''),
            ],
            'address_json' => is_array($data['address'] ?? null) ? $data['address'] : [
                'registered' => (string) ($data['registered_address'] ?? ''),
            ],
            'profile_json' => [
                'services_offered' => (string) ($data['services_offered'] ?? ''),
                'source' => is_array($data['source'] ?? null) ? $data['source'] : [],
            ],
            'source_submission_id' => $sourceSubmissionId !== '' ? $sourceSubmissionId : null,
            'is_active' => (bool) $row->is_active,
        ];

        $existing = ProcurementVendor::query()
            ->where('master_data_row_id', (string) $row->id)
            ->orWhere('vendor_code', $vendorCode)
            ->first();

        if ($existing instanceof ProcurementVendor) {
            $existing->fill($payload);
            $existing->save();

            return $existing;
        }

        return ProcurementVendor::query()->create(array_merge($payload, [
            'id' => (string) Str::uuid(),
            'accreditation_status' => ProcurementVendorAccreditationStatus::PENDING,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncComplianceDocuments(ProcurementVendor $vendor, array $data): void
    {
        $docs = is_array($data['compliance_documents'] ?? null) ? $data['compliance_documents'] : [];
        if ($docs === []) {
            return;
        }

        foreach ($docs as $doc) {
            if (! is_array($doc)) {
                continue;
            }

            $attachmentId = trim((string) ($doc['id'] ?? ''));
            if ($attachmentId === '') {
                continue;
            }

            $exists = ProcurementVendorDocument::query()
                ->where('vendor_id', $vendor->id)
                ->where('e_approval_attachment_id', $attachmentId)
                ->exists();

            if ($exists) {
                continue;
            }

            ProcurementVendorDocument::query()->create([
                'id' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'e_approval_attachment_id' => $attachmentId,
                'document_kind' => 'accreditation',
                'label' => (string) ($doc['file_name'] ?? __('Compliance document')),
                'file_name' => (string) ($doc['file_name'] ?? ''),
                'linked_at' => now(),
            ]);
        }
    }
}
