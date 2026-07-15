<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPaymentBatch;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;

final class ProcurementPaymentBatchExportService
{
    public function __construct(
        private readonly ProcurementVendorRegistryService $vendors,
    ) {}

    /** @return list<string> */
    public function headers(): array
    {
        return [
            'Batch No',
            'Payment Request No',
            'Vendor Code',
            'Vendor Name',
            'Bank Name',
            'Bank Account',
            'Amount',
            'Currency',
            'Scheduled Date',
            'AP Invoice No',
            'Vendor Invoice No',
            'Payment Reference',
        ];
    }

    /**
     * @return \Generator<int, list<string|float|null>>
     */
    public function rowsForBatch(ProcurementPaymentBatch $batch): \Generator
    {
        $batch->loadMissing(['paymentRequests.apInvoice']);

        foreach ($batch->paymentRequests as $request) {
            yield $this->rowForRequest($batch, $request);
        }
    }

    /** @return list<string|float|null> */
    private function rowForRequest(ProcurementPaymentBatch $batch, ProcurementPaymentRequest $request): array
    {
        $bankName = '';
        $bankAccount = '';
        if ($request->vendor_code !== null) {
            $vendor = $this->vendors->findByVendorCode((string) $request->vendor_code);
            if ($vendor !== null) {
                $banking = $vendor->banking_json ?? [];
                $bankName = (string) ($banking['bank_name'] ?? $banking['bank'] ?? '');
                $bankAccount = (string) ($banking['account_number'] ?? $banking['account_no'] ?? '');
            }
        }

        return [
            $batch->document_no,
            $request->document_no,
            $request->vendor_code,
            $request->vendor_name,
            $bankName,
            $bankAccount,
            (float) $request->amount,
            $request->currency_code,
            $request->scheduled_date?->format('Y-m-d') ?? $batch->scheduled_date?->format('Y-m-d'),
            $request->apInvoice?->document_no,
            $request->apInvoice?->vendor_invoice_no,
            $request->payment_reference,
        ];
    }
}
