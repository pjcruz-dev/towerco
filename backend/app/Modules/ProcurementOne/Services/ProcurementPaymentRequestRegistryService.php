<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPaymentRequestStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementPaymentRequestRegistryService
{
    private const SORTABLE = [
        'document_no',
        'status',
        'amount',
        'scheduled_date',
        'updated_at',
        'created_at',
    ];

    public function __construct(
        private readonly ProcurementApInvoiceOpenBalanceService $balances,
        private readonly ProcurementLifecycleAuditService $audit,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $vendorCode = null,
        ?string $apInvoiceId = null,
        ?string $sort = null,
    ): LengthAwarePaginator {
        $query = ProcurementPaymentRequest::query()
            ->with(['apInvoice:id,document_no,vendor_invoice_no', 'paymentBatch:id,document_no', 'requestor:id,name']);

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($vendorCode !== null && trim($vendorCode) !== '') {
            $query->where('vendor_code', trim($vendorCode));
        }

        if ($apInvoiceId !== null && $apInvoiceId !== '') {
            $query->where('ap_invoice_id', $apInvoiceId);
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhere('vendor_code', 'like', $like)
                    ->orWhere('vendor_name', 'like', $like)
                    ->orWhere('payment_reference', 'like', $like)
                    ->orWhereHas('apInvoice', static fn ($iq) => $iq
                        ->where('document_no', 'like', $like)
                        ->orWhere('vendor_invoice_no', 'like', $like));
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

    /**
     * @return list<array<string, mixed>>
     */
    public function paymentHistoryForVendor(string $vendorCode, int $limit = 50): array
    {
        $code = trim($vendorCode);
        if ($code === '') {
            return [];
        }

        return ProcurementPaymentRequest::query()
            ->with(['apInvoice:id,document_no,vendor_invoice_no', 'paymentBatch:id,document_no'])
            ->where('vendor_code', $code)
            ->whereNotIn('status', [ProcurementPaymentRequestStatus::DRAFT, ProcurementPaymentRequestStatus::CANCELLED])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProcurementPaymentRequest $request) => $this->toListPayload($request))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementPaymentRequest $request): array
    {
        return [
            'id' => (string) $request->id,
            'document_no' => $request->document_no,
            'status' => $request->status,
            'status_label' => ProcurementPaymentRequestStatus::label((string) $request->status),
            'ap_invoice_id' => (string) $request->ap_invoice_id,
            'ap_invoice_document_no' => $request->apInvoice?->document_no,
            'ap_vendor_invoice_no' => $request->apInvoice?->vendor_invoice_no,
            'payment_batch_id' => $request->payment_batch_id,
            'payment_batch_document_no' => $request->paymentBatch?->document_no,
            'vendor_code' => $request->vendor_code,
            'vendor_name' => $request->vendor_name,
            'amount' => (float) $request->amount,
            'currency_code' => $request->currency_code,
            'scheduled_date' => $request->scheduled_date?->format('Y-m-d'),
            'paid_at' => $request->paid_at?->toIso8601String(),
            'reconciled_at' => $request->reconciled_at?->toIso8601String(),
            'payment_reference' => $request->payment_reference,
            'requestor' => $request->requestor ? [
                'id' => (string) $request->requestor->id,
                'name' => $request->requestor->name,
            ] : null,
            'updated_at' => $request->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementPaymentRequest $request): array
    {
        $invoice = $request->apInvoice;

        return $this->toListPayload($request) + [
            'notes' => $request->notes,
            'approved_at' => $request->approved_at?->toIso8601String(),
            'metadata' => $request->metadata_json ?? [],
            'ap_invoice' => $invoice ? [
                'id' => (string) $invoice->id,
                'document_no' => $invoice->document_no,
                'vendor_invoice_no' => $invoice->vendor_invoice_no,
                'grand_total' => (float) $invoice->grand_total,
                'balance' => $this->balances->snapshotForInvoice($invoice),
            ] : null,
            'audit_trail' => $this->audit->listForDocument(
                ProcurementDocumentType::PAYMENT_REQUEST,
                (string) $request->id,
                50,
            ),
        ];
    }
}
