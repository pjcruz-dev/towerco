<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementPo;

final class ProcurementPoPrintEnrichmentService
{
    public function __construct(
        private readonly ProcurementPoValueMapper $mapper,
        private readonly ProcurementPoFormResolverService $formResolver,
        private readonly ProcurementVendorRegistryService $vendors,
    ) {}

    /**
     * Merge procurement PO registry values into E-Approval print field payloads.
     *
     * @param  array<string, array{label: string, value: string|null}>  $valuesByKey
     * @return array<string, array{label: string, value: string|null}>
     */
    public function enrichSubmissionPrintValues(EApprovalSubmission $submission, array $valuesByKey): array
    {
        $metadata = is_array($submission->form?->metadata_json) ? $submission->form->metadata_json : [];
        if (($metadata['form_family'] ?? null) !== 'purchase_order') {
            return $valuesByKey;
        }

        $po = ProcurementPo::query()
            ->with('lines')
            ->where('e_approval_submission_id', (string) $submission->id)
            ->first();

        if ($po === null) {
            return $this->applyFieldAliases($valuesByKey);
        }

        $mapped = $this->mapper->toEApprovalValues($po, $this->formResolver->primaryPrDocumentNo($po));
        $vendorLabel = $this->resolveVendorLabel($po, $mapped);

        $printFields = [
            'supplier' => $vendorLabel ?: ($mapped['supplier'] ?? null),
            'ship_to' => $mapped['ship_to'] ?? null,
            'delivery_date' => $mapped['required_delivery_date'] ?? $mapped['delivery_date'] ?? null,
            'payment_terms' => $mapped['payment_terms'] ?? null,
            'currency_code' => $mapped['currency_code'] ?? null,
            'exchange_rate' => $mapped['exchange_rate'] ?? null,
            'delivery_location' => $mapped['delivery_location'] ?? null,
            'vatable_amount' => $mapped['vatable_amount'] ?? null,
            'vat_exempt_amount' => $mapped['vat_exempt_amount'] ?? null,
            'zero_rated_amount' => $mapped['zero_rated_amount'] ?? null,
            'vat_rate' => $mapped['vat_rate'] ?? null,
            'vat_amount' => $mapped['vat_amount'] ?? null,
            'total_vat_inclusive' => $mapped['total_vat_inclusive'] ?? null,
            'less_discount' => $mapped['less_discount'] ?? null,
            'grand_total' => (string) $this->displayGrandTotal($po, $mapped),
            'total_amount' => $mapped['total_amount'] ?? null,
        ];

        foreach ($printFields as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $existing = trim((string) ($valuesByKey[$key]['value'] ?? ''));
            if ($existing !== '' && $existing !== '—') {
                continue;
            }

            $valuesByKey[$key] = [
                'label' => $valuesByKey[$key]['label'] ?? $this->labelForKey($key),
                'value' => (string) $value,
            ];
        }

        if ($vendorLabel !== '') {
            $valuesByKey['vendor'] = [
                'label' => $valuesByKey['vendor']['label'] ?? 'Vendor',
                'value' => $vendorLabel,
            ];
        }

        if (trim((string) ($valuesByKey['ship_to']['value'] ?? '')) === ''
            && trim((string) ($mapped['delivery_location'] ?? '')) !== '') {
            $valuesByKey['ship_to'] = [
                'label' => $valuesByKey['ship_to']['label'] ?? 'Ship to',
                'value' => (string) $mapped['delivery_location'],
            ];
        }

        return $this->applyFieldAliases($valuesByKey);
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    public function displayGrandTotal(ProcurementPo $po, array $mapped = []): float
    {
        $candidates = [
            (float) $po->grand_total,
            (float) $po->total_vat_inclusive,
            isset($mapped['grand_total']) ? (float) $mapped['grand_total'] : 0.0,
            isset($mapped['total_vat_inclusive']) ? (float) $mapped['total_vat_inclusive'] : 0.0,
            round((float) $po->vatable_amount + (float) $po->vat_amount, 2),
        ];

        foreach ($candidates as $amount) {
            if ($amount > 0) {
                return round($amount, 2);
            }
        }

        return 0.0;
    }

    /**
     * @param  array<string, array{label: string, value: string|null}>  $valuesByKey
     * @return array<string, array{label: string, value: string|null}>
     */
    private function applyFieldAliases(array $valuesByKey): array
    {
        $aliases = [
            'supplier' => ['supplier', 'vendor'],
            'delivery_date' => ['delivery_date', 'required_delivery_date'],
            'ship_to' => ['ship_to', 'delivery_location'],
            'grand_total' => ['grand_total', 'total_vat_inclusive', 'total_amount'],
        ];

        foreach ($aliases as $target => $sources) {
            if (trim((string) ($valuesByKey[$target]['value'] ?? '')) !== '') {
                continue;
            }

            foreach ($sources as $source) {
                $value = trim((string) ($valuesByKey[$source]['value'] ?? ''));
                if ($value === '') {
                    continue;
                }

                $valuesByKey[$target] = [
                    'label' => $valuesByKey[$target]['label'] ?? $this->labelForKey($target),
                    'value' => $value,
                ];
                break;
            }
        }

        return $valuesByKey;
    }

    /**
     * @param  array<string, mixed>  $mapped
     */
    private function resolveVendorLabel(ProcurementPo $po, array $mapped): string
    {
        $supplier = trim((string) ($po->supplier ?? $po->vendor_name ?? ''));
        if ($supplier !== '') {
            return $supplier;
        }

        $vendorCode = trim((string) ($mapped['vendor'] ?? $po->vendor_code ?? ''));
        if ($vendorCode === '') {
            return '';
        }

        $vendor = $this->vendors->findByVendorCode($vendorCode);

        return trim((string) ($vendor?->company_name ?? $vendorCode));
    }

    private function labelForKey(string $key): string
    {
        return match ($key) {
            'supplier' => 'Supplier',
            'ship_to' => 'Ship to',
            'delivery_date' => 'Delivery date',
            'payment_terms' => 'Payment terms',
            'currency_code' => 'Currency',
            'exchange_rate' => 'Exchange rate',
            'vatable_amount' => 'VATable amount',
            'vat_exempt_amount' => 'VAT-exempt amount',
            'zero_rated_amount' => 'Zero-rated amount',
            'vat_amount' => 'VAT amount',
            'total_vat_inclusive' => 'Total VAT inclusive',
            'less_discount' => 'Less discount',
            'grand_total' => 'Grand total',
            default => $key,
        };
    }
}
