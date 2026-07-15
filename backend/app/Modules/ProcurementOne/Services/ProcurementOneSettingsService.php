<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementDocumentTypeCatalog;
use App\Modules\ProcurementOne\Support\ProcurementNumberingSeriesCatalog;
use App\Modules\ProcurementOne\Support\ProcurementStatusCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementOneSettingsService
{
    public const DOCUMENT_TYPES = 'document_types';

    public const STATUS_CATALOGS = 'status_catalogs';

    public const NUMBERING_SERIES = 'numbering_series';

    public const MODULE_MESSAGE = 'module_message';

    public const VENDOR_ACCREDITATION_POLICY = 'vendor_accreditation_policy';

    public const PR_BUDGET_POLICY = 'pr_budget_policy';

    public const VENDOR_EMAIL_TEMPLATES = 'vendor_email_templates';

    public const GR_RECEIPT_POLICY = 'gr_receipt_policy';

    public const INVENTORY_POLICY = 'inventory_policy';

    public const AP_INVOICE_MATCH_POLICY = 'ap_invoice_match_policy';

    public const RFQ_SCORING_POLICY = 'rfq_scoring_policy';

    public const CONTRACT_SPEND_POLICY = 'contract_spend_policy';

    public const EXPORT_COLUMN_MAPS = 'export_column_maps';

    public const EXPORT_SCHEDULE = 'export_schedule';

    public function getString(string $key, ?string $default = null): ?string
    {
        $row = DB::connection('tenant')->table('procurement_one_settings')->where('key', $key)->first();
        if ($row === null || $row->value === null) {
            return $default;
        }

        return (string) $row->value;
    }

    public function setString(string $key, string $value): void
    {
        DB::connection('tenant')->table('procurement_one_settings')->updateOrInsert(
            ['key' => $key],
            ['value' => $value, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getJson(string $key): array
    {
        $raw = $this->getString($key);
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public function setJson(string $key, array $value): void
    {
        $this->setString($key, json_encode($value, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(): array
    {
        $documentTypes = app(ProcurementDocumentTypeCatalog::class)->resolve();
        $statusCatalogs = app(ProcurementStatusCatalog::class)->resolveAll();
        $numberingSeries = app(ProcurementNumberingSeriesCatalog::class)->resolveAll();

        return [
            'module_message' => $this->getString(self::MODULE_MESSAGE, ''),
            'vendor_accreditation_policy' => app(ProcurementVendorAccreditationPolicyService::class)->policy(),
            'pr_budget_policy' => app(ProcurementPrBudgetPolicyService::class)->policy(),
            'vendor_email_templates' => app(ProcurementVendorEmailTemplateService::class)->templates(),
            'gr_receipt_policy' => app(ProcurementGrnReceiptPolicyService::class)->policy(),
            'inventory_policy' => app(ProcurementInventoryPolicyService::class)->policy(),
            'ap_invoice_match_policy' => app(ProcurementApInvoiceMatchPolicyService::class)->policy(),
            'rfq_scoring_policy' => app(ProcurementRfqScoringPolicyService::class)->policy(),
            'contract_spend_policy' => app(ProcurementContractSpendPolicyService::class)->policy(),
            'export_column_maps' => app(ProcurementExportColumnMapService::class)->resolveAll(),
            'export_schedule' => app(ProcurementExportScheduleService::class)->policy(),
            'procurement_policy' => app(ProcurementFinancialPolicyService::class)->snapshot(),
            'document_types' => $documentTypes,
            'status_catalogs' => $statusCatalogs,
            'numbering_series' => $numberingSeries,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(array $values): void
    {
        if (array_key_exists('module_message', $values)) {
            $this->setString(self::MODULE_MESSAGE, trim((string) $values['module_message']));
        }

        if (array_key_exists('vendor_accreditation_policy', $values) && is_array($values['vendor_accreditation_policy'])) {
            $normalized = app(ProcurementVendorAccreditationPolicyService::class)
                ->validateAndNormalize($values['vendor_accreditation_policy']);
            $this->setJson(self::VENDOR_ACCREDITATION_POLICY, $normalized);
        }

        if (array_key_exists('pr_budget_policy', $values) && is_array($values['pr_budget_policy'])) {
            $normalized = app(ProcurementPrBudgetPolicyService::class)
                ->validateAndNormalize($values['pr_budget_policy']);
            $this->setJson(self::PR_BUDGET_POLICY, $normalized);
        }

        if (array_key_exists('vendor_email_templates', $values) && is_array($values['vendor_email_templates'])) {
            $normalized = app(ProcurementVendorEmailTemplateService::class)
                ->validateAndNormalize($values['vendor_email_templates']);
            $this->setJson(self::VENDOR_EMAIL_TEMPLATES, $normalized);
        }

        if (array_key_exists('gr_receipt_policy', $values) && is_array($values['gr_receipt_policy'])) {
            $normalized = app(ProcurementGrnReceiptPolicyService::class)
                ->validateAndNormalize($values['gr_receipt_policy']);
            $this->setJson(self::GR_RECEIPT_POLICY, $normalized);
        }

        if (array_key_exists('inventory_policy', $values) && is_array($values['inventory_policy'])) {
            $normalized = app(ProcurementInventoryPolicyService::class)
                ->validateAndNormalize($values['inventory_policy']);
            $this->setJson(self::INVENTORY_POLICY, $normalized);
        }

        if (array_key_exists('ap_invoice_match_policy', $values) && is_array($values['ap_invoice_match_policy'])) {
            $normalized = app(ProcurementApInvoiceMatchPolicyService::class)
                ->validateAndNormalize($values['ap_invoice_match_policy']);
            $this->setJson(self::AP_INVOICE_MATCH_POLICY, $normalized);
        }

        if (array_key_exists('rfq_scoring_policy', $values) && is_array($values['rfq_scoring_policy'])) {
            $normalized = app(ProcurementRfqScoringPolicyService::class)
                ->validateAndNormalize($values['rfq_scoring_policy']);
            $this->setJson(self::RFQ_SCORING_POLICY, $normalized);
        }

        if (array_key_exists('contract_spend_policy', $values) && is_array($values['contract_spend_policy'])) {
            $normalized = app(ProcurementContractSpendPolicyService::class)
                ->validateAndNormalize($values['contract_spend_policy']);
            $this->setJson(self::CONTRACT_SPEND_POLICY, $normalized);
        }

        if (array_key_exists('export_column_maps', $values) && is_array($values['export_column_maps'])) {
            $normalized = app(ProcurementExportColumnMapService::class)
                ->validateAndNormalize($values['export_column_maps']);
            $this->setJson(self::EXPORT_COLUMN_MAPS, $normalized);
        }

        if (array_key_exists('export_schedule', $values) && is_array($values['export_schedule'])) {
            $existing = app(ProcurementExportScheduleService::class)->policy();
            $normalized = app(ProcurementExportScheduleService::class)
                ->validateAndNormalize(array_merge($existing, $values['export_schedule']));
            $this->setJson(self::EXPORT_SCHEDULE, $normalized);
        }

        if (array_key_exists('procurement_policy', $values) && is_array($values['procurement_policy'])) {
            $normalized = app(ProcurementFinancialPolicyService::class)
                ->validateAndNormalize($values['procurement_policy']);
            app(ProcurementFinancialPolicyService::class)->persist($normalized);
        }

        if (array_key_exists('document_types', $values) && is_array($values['document_types'])) {
            $types = [];
            foreach ($values['document_types'] as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $id = trim((string) ($item['id'] ?? ''));
                if ($id === '' || ! ProcurementDocumentType::isValid($id)) {
                    throw ValidationException::withMessages([
                        'document_types' => [__('Document type id is invalid.')],
                    ]);
                }

                $label = trim((string) ($item['label'] ?? ''));
                if ($label === '') {
                    throw ValidationException::withMessages([
                        'document_types' => [__('Document type label is required.')],
                    ]);
                }

                $code = strtoupper(trim((string) ($item['code'] ?? '')));
                if ($code === '' || ! preg_match('/^[A-Z0-9_]+$/', $code)) {
                    throw ValidationException::withMessages([
                        'document_types' => [__('Document type code must use uppercase letters, numbers, and underscores.')],
                    ]);
                }

                $types[] = ['id' => $id, 'label' => $label, 'code' => $code];
            }

            if ($types === []) {
                throw ValidationException::withMessages([
                    'document_types' => [__('At least one document type is required.')],
                ]);
            }

            $this->setJson(self::DOCUMENT_TYPES, $types);
        }

        if (array_key_exists('status_catalogs', $values) && is_array($values['status_catalogs'])) {
            $normalized = app(ProcurementStatusCatalog::class)
                ->validateAndNormalizePayload($values['status_catalogs']);
            if ($normalized !== []) {
                $existing = $this->getJson(self::STATUS_CATALOGS);
                $this->setJson(self::STATUS_CATALOGS, array_merge($existing, $normalized));
            }
        }

        if (array_key_exists('numbering_series', $values) && is_array($values['numbering_series'])) {
            $normalized = app(ProcurementNumberingSeriesCatalog::class)
                ->validateAndNormalizePayload($values['numbering_series']);
            if ($normalized !== []) {
                $existing = $this->getJson(self::NUMBERING_SERIES);
                $this->setJson(self::NUMBERING_SERIES, array_merge($existing, $normalized));
            }
        }
    }
}
