<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

use App\Modules\EApproval\Models\EApprovalMasterDataRow;
use App\Modules\EApproval\Models\EApprovalMasterDataSet;

final class EApprovalVendorMasterDataDedupeService
{
    public const MATCH_TAX_ID = 'tax_id';

    public const MATCH_COMPANY_NAME = 'company_name';

    /** @var array<string, mixed> */
    private array $config;

    public function __construct()
    {
        $this->config = config('e_approval_vendor_master_data', []);
    }

    public function normalizeTaxId(string $taxId): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim($taxId)) ?? '');
    }

    public function normalizeCompanyName(string $companyName): string
    {
        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', trim($companyName)) ?? '');

        if ($normalized === '') {
            return '';
        }

        $suffixes = $this->companyNameSuffixes();
        $changed = true;

        while ($changed && $normalized !== '') {
            $changed = false;

            foreach ($suffixes as $suffix) {
                if (
                    strlen($normalized) > strlen($suffix) + 2
                    && str_ends_with($normalized, $suffix)
                ) {
                    $normalized = substr($normalized, 0, -strlen($suffix));
                    $changed = true;
                    break;
                }
            }
        }

        return $normalized;
    }

    public function normalizeContactEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    /**
     * @return array{row: EApprovalMasterDataRow, matched_by: string}|null
     */
    public function findExistingRow(
        EApprovalMasterDataSet $set,
        string $taxId,
        string $companyName,
        string $contactEmail,
    ): ?array {
        $normalizedTaxId = $this->normalizeTaxId($taxId);
        $normalizedCompanyName = $this->normalizeCompanyName($companyName);
        $normalizedEmail = $this->normalizeContactEmail($contactEmail);

        if ($normalizedTaxId !== '') {
            $byTaxId = $this->findByTaxId($set, $normalizedTaxId);
            if ($byTaxId instanceof EApprovalMasterDataRow) {
                return [
                    'row' => $byTaxId,
                    'matched_by' => self::MATCH_TAX_ID,
                ];
            }
        }

        if ($normalizedCompanyName === '' || ! $this->matchesByCompanyNameEnabled()) {
            return null;
        }

        $byCompanyName = $this->findByCompanyName(
            $set,
            $normalizedCompanyName,
            $normalizedTaxId,
            $normalizedEmail,
        );

        if ($byCompanyName instanceof EApprovalMasterDataRow) {
            return [
                'row' => $byCompanyName,
                'matched_by' => self::MATCH_COMPANY_NAME,
            ];
        }

        return null;
    }

    private function findByTaxId(EApprovalMasterDataSet $set, string $normalizedTaxId): ?EApprovalMasterDataRow
    {
        /** @var EApprovalMasterDataRow|null $byCode */
        $byCode = $set->rows()->where('code', $normalizedTaxId)->first();
        if ($byCode instanceof EApprovalMasterDataRow) {
            return $byCode;
        }

        foreach ($set->rows()->get() as $row) {
            $data = is_array($row->data_json) ? $row->data_json : [];
            $rowTaxId = trim((string) ($data['tax_id_normalized'] ?? $data['tax_id'] ?? $row->code ?? ''));

            if ($rowTaxId !== '' && $this->normalizeTaxId($rowTaxId) === $normalizedTaxId) {
                return $row;
            }
        }

        return null;
    }

    private function findByCompanyName(
        EApprovalMasterDataSet $set,
        string $normalizedCompanyName,
        string $incomingTaxId,
        string $incomingEmail,
    ): ?EApprovalMasterDataRow {
        foreach ($set->rows()->get() as $row) {
            $data = is_array($row->data_json) ? $row->data_json : [];
            $rowCompanyName = trim((string) ($data['company_name_normalized'] ?? ''));
            if ($rowCompanyName === '') {
                $rowCompanyName = $this->normalizeCompanyName((string) ($data['company_name'] ?? $row->label ?? ''));
            }

            if ($rowCompanyName === '' || $rowCompanyName !== $normalizedCompanyName) {
                continue;
            }

            if (! $this->passesCompanyNameGuards($data, $incomingTaxId, $incomingEmail)) {
                continue;
            }

            return $row;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $existingData
     */
    private function passesCompanyNameGuards(array $existingData, string $incomingTaxId, string $incomingEmail): bool
    {
        if ($this->companyNameRequiresEmailMatch()) {
            $existingEmail = $this->normalizeContactEmail((string) (
                $existingData['contact']['email']
                ?? $existingData['contact_email']
                ?? ''
            ));

            if ($existingEmail === '' || $incomingEmail === '' || $existingEmail !== $incomingEmail) {
                return false;
            }
        }

        if (! $this->blockMergeOnTaxIdConflict()) {
            return true;
        }

        $existingTaxId = trim((string) ($existingData['tax_id_normalized'] ?? $existingData['tax_id'] ?? ''));
        $existingTaxId = $this->normalizeTaxId($existingTaxId);

        if ($existingTaxId === '' || $incomingTaxId === '') {
            return true;
        }

        return $existingTaxId === $incomingTaxId;
    }

    private function matchesByCompanyNameEnabled(): bool
    {
        $matchBy = $this->config['dedupe']['match_by'] ?? [];

        return is_array($matchBy) && in_array(self::MATCH_COMPANY_NAME, $matchBy, true);
    }

    private function companyNameRequiresEmailMatch(): bool
    {
        return (bool) ($this->config['dedupe']['company_name_requires_email_match'] ?? false);
    }

    private function blockMergeOnTaxIdConflict(): bool
    {
        return (bool) ($this->config['dedupe']['block_merge_on_tax_id_conflict'] ?? true);
    }

    /**
     * @return list<string>
     */
    private function companyNameSuffixes(): array
    {
        $suffixes = $this->config['dedupe']['company_name_suffixes'] ?? [
            'INCORPORATED',
            'CORPORATION',
            'COMPANY',
            'INC',
            'CORP',
            'LTD',
            'CO',
        ];

        return is_array($suffixes) ? array_values(array_filter($suffixes, is_string(...))) : [];
    }
}
