<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementCreditNote;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementCreditNoteStatus;
use App\Modules\ProcurementOne\Support\ProcurementPaymentRequestStatus;

final class ProcurementApInvoiceOpenBalanceService
{
    public function creditAmountForInvoice(string $invoiceId): float
    {
        return round((float) ProcurementCreditNote::query()
            ->where('ap_invoice_id', $invoiceId)
            ->where('status', ProcurementCreditNoteStatus::APPROVED)
            ->sum('amount'), 2);
    }

    public function encumberedPaymentAmountForInvoice(string $invoiceId, ?string $excludeRequestId = null): float
    {
        $query = ProcurementPaymentRequest::query()
            ->where('ap_invoice_id', $invoiceId)
            ->whereIn('status', ProcurementPaymentRequestStatus::encumbering());

        if ($excludeRequestId !== null) {
            $query->where('id', '!=', $excludeRequestId);
        }

        return round((float) $query->sum('amount'), 2);
    }

    public function paidAmountForInvoice(string $invoiceId): float
    {
        return round((float) ProcurementPaymentRequest::query()
            ->where('ap_invoice_id', $invoiceId)
            ->whereIn('status', [ProcurementPaymentRequestStatus::PAID, ProcurementPaymentRequestStatus::RECONCILED])
            ->sum('amount'), 2);
    }

    public function openPayableForInvoice(ProcurementApInvoice|string $invoice, ?string $excludeRequestId = null): float
    {
        $model = $invoice instanceof ProcurementApInvoice
            ? $invoice
            : ProcurementApInvoice::query()->find($invoice);

        if ($model === null || (string) $model->status !== ProcurementApInvoiceStatus::APPROVED) {
            return 0.0;
        }

        $invoiceId = (string) $model->id;
        $open = (float) $model->grand_total
            - $this->creditAmountForInvoice($invoiceId)
            - $this->encumberedPaymentAmountForInvoice($invoiceId, $excludeRequestId);

        return max(0, round($open, 2));
    }

    /**
     * @return array{
     *   grand_total: float,
     *   credit_total: float,
     *   encumbered_total: float,
     *   paid_total: float,
     *   open_payable: float
     * }
     */
    public function snapshotForInvoice(ProcurementApInvoice $invoice): array
    {
        $invoiceId = (string) $invoice->id;
        $grandTotal = round((float) $invoice->grand_total, 2);
        $creditTotal = $this->creditAmountForInvoice($invoiceId);
        $encumberedTotal = $this->encumberedPaymentAmountForInvoice($invoiceId);
        $paidTotal = $this->paidAmountForInvoice($invoiceId);
        $openPayable = $this->openPayableForInvoice($invoice);

        return [
            'grand_total' => $grandTotal,
            'credit_total' => $creditTotal,
            'encumbered_total' => $encumberedTotal,
            'paid_total' => $paidTotal,
            'open_payable' => $openPayable,
        ];
    }
}
