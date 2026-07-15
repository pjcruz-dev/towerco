<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementComposeMetadata
{
    public const COMPOSE_FORM_VALUES_KEY = 'compose_form_values';

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public static function composeFormValues(?array $metadata): array
    {
        if (! is_array($metadata)) {
            return [];
        }

        $compose = $metadata[self::COMPOSE_FORM_VALUES_KEY] ?? null;

        return is_array($compose) ? $compose : [];
    }

    /**
     * @param  array<string, mixed>|null  $existingMetadata
     * @param  array<string, mixed>|null  $composeFormValues
     * @return array<string, mixed>
     */
    public static function withComposeFormValues(?array $existingMetadata, ?array $composeFormValues): array
    {
        if ($composeFormValues === null) {
            return is_array($existingMetadata) ? $existingMetadata : [];
        }

        $metadata = is_array($existingMetadata) ? $existingMetadata : [];
        $metadata[self::COMPOSE_FORM_VALUES_KEY] = $composeFormValues;

        return $metadata;
    }

    /**
     * @return list<string>
     */
    public static function purchaseOrderBaseFieldKeys(): array
    {
        return [
            'purchase_requisition_document_no',
            'vendor',
            'supplier',
            'ship_to',
            'delivery_date',
            'required_delivery_date',
            'delivery_location',
            'payment_terms',
            'currency_code',
            'exchange_rate',
            'line_items',
            'vatable_amount',
            'vat_exempt_amount',
            'zero_rated_amount',
            'vat_rate',
            'vat_amount',
            'total_vat_inclusive',
            'less_discount',
            'grand_total',
            'total_amount',
        ];
    }

    /**
     * @return list<string>
     */
    public static function apInvoiceBaseFieldKeys(): array
    {
        return [
            'purchase_order_document_no',
            'vendor_invoice_no',
            'supplier',
            'invoice_date',
            'due_date',
            'payment_terms',
            'currency_code',
            'line_items',
            'vatable_amount',
            'vat_amount',
            'grand_total',
            'total_amount',
        ];
    }

    /**
     * @return list<string>
     */
    public static function purchaseRequisitionBaseFieldKeys(): array
    {
        return [
            'requisition_title',
            'department',
            'urgency',
            'line_items',
            'estimated_total',
            'justification',
            'currency',
            'currency_code',
        ];
    }
}
