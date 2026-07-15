<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Support;

final class ProcurementDocumentType
{
    public const PURCHASE_REQUISITION = 'purchase_requisition';

    public const PURCHASE_ORDER = 'purchase_order';

    public const GOODS_RECEIPT = 'goods_receipt';

    public const AP_INVOICE = 'ap_invoice';

    public const CREDIT_NOTE = 'credit_note';

    public const PAYMENT_REQUEST = 'payment_request';

    public const PAYMENT_BATCH = 'payment_batch';

    public const REQUEST_FOR_QUOTATION = 'request_for_quotation';

    public const VENDOR_CONTRACT = 'vendor_contract';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::PURCHASE_REQUISITION,
            self::PURCHASE_ORDER,
            self::GOODS_RECEIPT,
            self::AP_INVOICE,
            self::CREDIT_NOTE,
            self::PAYMENT_REQUEST,
            self::PAYMENT_BATCH,
            self::REQUEST_FOR_QUOTATION,
            self::VENDOR_CONTRACT,
        ];
    }

    public static function isValid(string $type): bool
    {
        return in_array($type, self::all(), true);
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::PURCHASE_REQUISITION => 'Purchase requisition',
            self::PURCHASE_ORDER => 'Purchase order',
            self::GOODS_RECEIPT => 'Goods receipt',
            self::AP_INVOICE => 'AP invoice',
            self::CREDIT_NOTE => 'Credit note',
            self::PAYMENT_REQUEST => 'Payment request',
            self::PAYMENT_BATCH => 'Payment batch',
            self::REQUEST_FOR_QUOTATION => 'Request for quotation',
            self::VENDOR_CONTRACT => 'Vendor contract',
            default => $type,
        };
    }
}
