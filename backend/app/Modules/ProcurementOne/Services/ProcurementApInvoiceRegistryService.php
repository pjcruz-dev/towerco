<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementCreditNote;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementApMatchMode;
use App\Modules\ProcurementOne\Support\ProcurementCreditNoteStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementApInvoiceRegistryService
{
    private const SORTABLE = [
        'document_no',
        'vendor_invoice_no',
        'status',
        'due_date',
        'grand_total',
        'updated_at',
        'created_at',
    ];

    public function __construct(
        private readonly ProcurementApInvoicePoBalanceService $balances,
        private readonly ProcurementApInvoiceOpenBalanceService $openBalances,
        private readonly ProcurementPaymentRequestRegistryService $payments,
        private readonly ProcurementComposeValuesResolver $composeValues,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $poId = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = ProcurementApInvoice::query()
            ->with(['purchaseOrder:id,document_no,supplier,vendor_name', 'requestor:id,name']);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($poId !== null && $poId !== '') {
            $query->where('po_id', $poId);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhere('vendor_invoice_no', 'like', $like)
                    ->orWhereHas('purchaseOrder', static fn ($pq) => $pq
                        ->where('document_no', 'like', $like)
                        ->orWhere('supplier', 'like', $like));
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'updated_at:desc'),
            self::SORTABLE,
            'updated_at',
            'desc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $id): ?ProcurementApInvoice
    {
        return ProcurementApInvoice::query()
            ->with([
                'lines.poLine',
                'purchaseOrder.lines',
                'goodsReceipt.lines',
                'requestor:id,name,email',
                'creditNotes',
                'paymentRequests',
            ])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementApInvoice $invoice): array
    {
        return [
            'id' => (string) $invoice->id,
            'document_no' => $invoice->document_no,
            'status' => $invoice->status,
            'status_label' => ProcurementApInvoiceStatus::label((string) $invoice->status),
            'vendor_invoice_no' => $invoice->vendor_invoice_no,
            'po_id' => (string) $invoice->po_id,
            'po_document_no' => $invoice->purchaseOrder?->document_no,
            'po_supplier' => $invoice->purchaseOrder?->supplier ?? $invoice->purchaseOrder?->vendor_name,
            'grn_id' => $invoice->grn_id,
            'grand_total' => (float) $invoice->grand_total,
            'match_mode' => $invoice->match_mode,
            'match_mode_label' => ProcurementApMatchMode::label((string) $invoice->match_mode),
            'match_status' => $invoice->match_status,
            'invoice_date' => $invoice->invoice_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'approved_at' => $invoice->approved_at?->toIso8601String(),
            'updated_at' => $invoice->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementApInvoice $invoice): array
    {
        $po = $invoice->purchaseOrder;

        return $this->toListPayload($invoice) + [
            'currency_code' => $invoice->currency_code,
            'exchange_rate' => (float) $invoice->exchange_rate,
            'vatable_amount' => (float) $invoice->vatable_amount,
            'vat_exempt_amount' => (float) $invoice->vat_exempt_amount,
            'zero_rated_amount' => (float) $invoice->zero_rated_amount,
            'vat_rate' => (float) $invoice->vat_rate,
            'vat_amount' => (float) $invoice->vat_amount,
            'total_vat_inclusive' => (float) $invoice->total_vat_inclusive,
            'less_discount' => (float) $invoice->less_discount,
            'match_variance_amount' => (float) $invoice->match_variance_amount,
            'e_approval_submission_id' => $invoice->e_approval_submission_id,
            'compose_values' => $this->composeValues->forApInvoice($invoice),
            'notes' => $invoice->notes,
            'metadata' => $invoice->metadata_json ?? [],
            'requestor' => $invoice->requestor ? [
                'id' => (string) $invoice->requestor->id,
                'name' => $invoice->requestor->name,
                'email' => $invoice->requestor->email,
            ] : null,
            'purchase_order' => $po ? [
                'id' => (string) $po->id,
                'document_no' => $po->document_no,
                'status' => $po->status,
                'supplier' => $po->supplier,
                'grand_total' => (float) $po->grand_total,
                'invoiced_total' => $this->balances->invoicedAmountForPo((string) $po->id),
            ] : null,
            'goods_receipt' => $invoice->goodsReceipt ? [
                'id' => (string) $invoice->goodsReceipt->id,
                'document_no' => $invoice->goodsReceipt->document_no,
                'status' => $invoice->goodsReceipt->status,
            ] : null,
            'lines' => $invoice->lines->map(static fn ($line) => [
                'id' => (string) $line->id,
                'line_order' => $line->line_order,
                'po_line_id' => (string) $line->po_line_id,
                'grn_line_id' => $line->grn_line_id,
                'description' => $line->description,
                'uom' => $line->uom,
                'quantity_invoiced' => (float) $line->quantity_invoiced,
                'unit_price' => (float) $line->unit_price,
                'discount' => (float) $line->discount,
                'amount' => (float) $line->amount,
                'cost_center_id' => $line->cost_center_id,
                'expense_type' => $line->expense_type,
                'budget_line_id' => $line->budget_line_id,
            ])->values()->all(),
            'credit_notes' => $invoice->creditNotes->map(static fn (ProcurementCreditNote $note) => [
                'id' => (string) $note->id,
                'document_no' => $note->document_no,
                'status' => $note->status,
                'status_label' => ProcurementCreditNoteStatus::label((string) $note->status),
                'amount' => (float) $note->amount,
                'vendor_credit_note_no' => $note->vendor_credit_note_no,
                'credit_date' => $note->credit_date?->format('Y-m-d'),
            ])->values()->all(),
            'payment_balance' => $this->openBalances->snapshotForInvoice($invoice),
            'payment_requests' => $invoice->paymentRequests
                ->map(fn ($request) => $this->payments->toListPayload($request))
                ->values()
                ->all(),
        ];
    }
}
