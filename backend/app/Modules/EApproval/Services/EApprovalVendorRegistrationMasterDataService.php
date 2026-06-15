<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalForm;
use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;
use App\Modules\EApproval\Models\EApprovalSubmission;
use Illuminate\Support\Str;

final class EApprovalVendorRegistrationMasterDataService
{
    public const VENDORS_SET_KEY = 'vendors';

    public function __construct(
        private readonly EApprovalMasterDataService $masterData,
        private readonly EApprovalAuditLogger $audit,
        private readonly EApprovalVendorMasterDataMapper $mapper,
        private readonly EApprovalVendorMasterDataDedupeService $dedupe,
    ) {}

    public function syncApprovedRegistration(EApprovalSubmission $submission, ?\Illuminate\Contracts\Auth\Authenticatable $actor = null): ?EApprovalMasterDataRow
    {
        $submission->loadMissing(['form', 'values.field', 'attachments']);

        if (! $this->isVendorRegistrationSubmission($submission)) {
            return null;
        }

        $payload = $this->mapper->mapSubmission($submission);
        $companyName = trim((string) ($payload['company_name'] ?? ''));
        $taxId = trim((string) ($payload['tax_id'] ?? ''));
        $contactEmail = trim((string) ($payload['contact']['email'] ?? $payload['contact_email'] ?? ''));

        if ($companyName === '' || $taxId === '') {
            return null;
        }

        $set = $this->ensureVendorsSet();
        $match = $this->dedupe->findExistingRow($set, $taxId, $companyName, $contactEmail);

        if (is_array($match)) {
            $existing = $match['row'];
            $matchedBy = (string) $match['matched_by'];
            $payload = $this->mapper->withDedupeMetadata($payload, $matchedBy, (string) $existing->id);

            $row = $this->masterData->updateRow($existing, [
                'code' => $this->vendorCode($taxId),
                'label' => $companyName,
                'data' => $payload,
                'is_active' => true,
            ]);

            $this->audit->log(
                $matchedBy === EApprovalVendorMasterDataDedupeService::MATCH_COMPANY_NAME
                    ? 'vendor_master_data_deduped'
                    : 'vendor_master_data_updated',
                (string) $submission->id,
                $this->auditRemarks((string) $row->id, $matchedBy),
                $actor,
            );

            return $row;
        }

        $row = $this->masterData->createRow($set, [
            'code' => $this->vendorCode($taxId),
            'label' => $companyName,
            'data' => $payload,
            'is_active' => true,
        ]);

        $this->audit->log(
            'vendor_master_data_created',
            (string) $submission->id,
            (string) $row->id,
            $actor,
        );

        return $row;
    }

    private function isVendorRegistrationSubmission(EApprovalSubmission $submission): bool
    {
        if (! $submission->form instanceof EApprovalForm) {
            return false;
        }

        $metadata = is_array($submission->form->metadata_json) ? $submission->form->metadata_json : [];

        return ($metadata['form_family'] ?? null) === 'vendor_registration';
    }

    private function ensureVendorsSet(): EApprovalMasterDataSet
    {
        $setKey = (string) (config('e_approval_vendor_master_data.set_key') ?? self::VENDORS_SET_KEY);
        $setName = (string) (config('e_approval_vendor_master_data.set_name') ?? 'Vendors');
        $setConfig = $this->mapper->setConfig();

        $existing = EApprovalMasterDataSet::query()
            ->where('key', $setKey)
            ->first();

        if ($existing instanceof EApprovalMasterDataSet) {
            if (($existing->config_json['schema_version'] ?? null) !== $this->mapper->schemaVersion()) {
                $this->masterData->updateSet($existing, [
                    'config_json' => $setConfig,
                ]);
            }

            return $existing->refresh();
        }

        return $this->masterData->createSet([
            'key' => $setKey,
            'name' => $setName,
            'status' => 'active',
            'config_json' => $setConfig,
        ]);
    }

    private function vendorCode(string $taxId): string
    {
        $normalized = $this->dedupe->normalizeTaxId($taxId);
        if ($normalized !== '') {
            return $normalized;
        }

        return 'vendor-'.strtolower(substr((string) Str::uuid(), 0, 8));
    }

    private function auditRemarks(string $rowId, string $matchedBy): string
    {
        return "row:{$rowId};matched_by:{$matchedBy}";
    }
}
