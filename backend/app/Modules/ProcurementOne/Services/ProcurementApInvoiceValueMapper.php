<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementApInvoiceLine;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Support\ProcurementGridValueParser;

final class ProcurementApInvoiceValueMapper
{
    public function __construct(
        private readonly ProcurementGridValueParser $gridParser,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toEApprovalValues(ProcurementApInvoice $invoice, ?string $poDocumentNo = null): array
    {
        $invoice->loadMissing('lines', 'purchaseOrder');

        $gridRows = [];
        foreach ($invoice->lines as $line) {
            $gridRows[] = [
                'Description' => $line->description,
                'UOM' => $line->uom ?? 'EA',
                'Qty' => (string) $line->quantity_invoiced,
                'Unit price' => (string) $line->unit_price,
                'Amount' => (string) $line->amount,
            ];
        }

        return array_filter([
            'purchase_order_document_no' => $poDocumentNo ?? $invoice->purchaseOrder?->document_no,
            'vendor_invoice_no' => $invoice->vendor_invoice_no,
            'supplier' => $invoice->vendor_name ?? $invoice->purchaseOrder?->supplier,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'payment_terms' => $invoice->payment_terms,
            'currency_code' => $invoice->currency_code,
            'line_items' => $gridRows,
            'vatable_amount' => (string) $invoice->vatable_amount,
            'vat_amount' => (string) $invoice->vat_amount,
            'grand_total' => (string) $invoice->grand_total,
            'total_amount' => (string) $invoice->grand_total,
        ], static fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @param  list<array{po_line_id: string, grn_line_id?: string|null, description?: string, uom?: string, quantity_invoiced?: float, unit_price?: float, discount?: float, cost_center_id?: string, expense_type?: string, budget_line_id?: string}>  $lines
     */
    public function syncLines(ProcurementApInvoice $invoice, array $lines): void
    {
        $invoice->lines()->delete();

        foreach (array_values($lines) as $index => $line) {
            $qty = max(0, (float) ($line['quantity_invoiced'] ?? 0));
            $unitPrice = max(0, (float) ($line['unit_price'] ?? 0));
            $discount = max(0, (float) ($line['discount'] ?? 0));
            $amount = max(0, round(($qty * $unitPrice) - $discount, 2));

            ProcurementApInvoiceLine::query()->create([
                'ap_invoice_id' => $invoice->id,
                'po_line_id' => $line['po_line_id'],
                'grn_line_id' => $line['grn_line_id'] ?? null,
                'line_order' => $index,
                'description' => (string) ($line['description'] ?? ''),
                'uom' => $line['uom'] ?? 'EA',
                'quantity_invoiced' => $qty,
                'unit_price' => $unitPrice,
                'discount' => $discount,
                'amount' => $amount,
                'cost_center_id' => $line['cost_center_id'] ?? null,
                'expense_type' => $line['expense_type'] ?? null,
                'budget_line_id' => $line['budget_line_id'] ?? null,
            ]);
        }
    }

    /**
     * @param  list<string>|null  $columnLabels
     * @return list<array{po_line_id: string, grn_line_id?: string|null, description: string, uom: string, quantity_invoiced: float, unit_price: float, discount: float}>
     */
    public function linesFromGridValue(mixed $raw, ProcurementPo $po, ?array $columnLabels = null): array
    {
        $po->loadMissing('lines');
        $poLines = $po->lines->values()->all();
        $labels = $columnLabels ?? ['Description', 'UOM', 'Qty', 'Unit price', 'Amount'];
        $rows = $this->gridParser->labeledRows($raw, $labels);

        if ($rows === []) {
            return [];
        }

        $lines = [];
        foreach (array_values($rows) as $index => $row) {
            $poLine = $poLines[$index] ?? null;
            if ($poLine === null) {
                continue;
            }

            $quantity = (float) ($row['Qty'] ?? $row['quantity'] ?? $row['quantity_invoiced'] ?? $poLine->quantity);
            $unitPrice = (float) ($row['Unit price'] ?? $row['unit_price'] ?? $poLine->unit_price);
            $discount = (float) ($row['Discount'] ?? $row['discount'] ?? 0);

            $lines[] = [
                'po_line_id' => (string) $poLine->id,
                'description' => trim((string) ($row['Description'] ?? $row['description'] ?? $poLine->description)),
                'uom' => (string) ($row['UOM'] ?? $row['uom'] ?? $poLine->uom ?? 'EA'),
                'quantity_invoiced' => $quantity,
                'unit_price' => $unitPrice,
                'discount' => $discount,
            ];
        }

        return $lines;
    }
}
