<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementCreditNote;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementCreditNoteStatus;
use Generator;

final class ProcurementApInvoiceGlExportService
{
    private const MAX_ROWS = 5000;

    /**
     * @return list<string>
     */
    public function headers(): array
    {
        return [
            'document_no',
            'vendor_invoice_no',
            'po_document_no',
            'grn_document_no',
            'supplier',
            'invoice_date',
            'due_date',
            'line_description',
            'expense_type',
            'cost_center_id',
            'quantity',
            'unit_price',
            'line_amount',
            'vat_amount',
            'grand_total',
            'currency_code',
            'status',
            'approved_at',
        ];
    }

    /**
     * @param  array{status?: string, from?: string, to?: string}  $filters
     * @return Generator<int, list<string>>
     */
    public function rows(array $filters): Generator
    {
        $query = ProcurementApInvoice::query()
            ->with(['lines', 'purchaseOrder', 'goodsReceipt'])
            ->where('status', ProcurementApInvoiceStatus::APPROVED)
            ->orderByDesc('approved_at')
            ->limit(self::MAX_ROWS);

        if (! empty($filters['from'])) {
            $query->where('approved_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('approved_at', '<=', $filters['to']);
        }

        $creditTotals = ProcurementCreditNote::query()
            ->where('status', ProcurementCreditNoteStatus::APPROVED)
            ->whereNotNull('ap_invoice_id')
            ->get()
            ->groupBy('ap_invoice_id')
            ->map(static fn ($notes) => (float) $notes->sum('amount'));

        foreach ($query->cursor() as $invoice) {
            $credit = $creditTotals[(string) $invoice->id] ?? 0.0;
            if ($credit >= (float) $invoice->grand_total) {
                continue;
            }

            foreach ($invoice->lines as $line) {
                yield [
                    (string) ($invoice->document_no ?? ''),
                    (string) ($invoice->vendor_invoice_no ?? ''),
                    (string) ($invoice->purchaseOrder?->document_no ?? ''),
                    (string) ($invoice->goodsReceipt?->document_no ?? ''),
                    (string) ($invoice->vendor_name ?? $invoice->purchaseOrder?->supplier ?? ''),
                    (string) ($invoice->invoice_date?->format('Y-m-d') ?? ''),
                    (string) ($invoice->due_date?->format('Y-m-d') ?? ''),
                    (string) $line->description,
                    (string) ($line->expense_type ?? ''),
                    (string) ($line->cost_center_id ?? ''),
                    number_format((float) $line->quantity_invoiced, 4, '.', ''),
                    number_format((float) $line->unit_price, 4, '.', ''),
                    number_format((float) $line->amount, 2, '.', ''),
                    number_format((float) $invoice->vat_amount, 2, '.', ''),
                    number_format((float) $invoice->grand_total, 2, '.', ''),
                    (string) $invoice->currency_code,
                    (string) $invoice->status,
                    (string) ($invoice->approved_at?->toIso8601String() ?? ''),
                ];
            }
        }
    }
}
