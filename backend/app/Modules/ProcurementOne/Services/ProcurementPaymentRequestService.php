<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPaymentRequestStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementPaymentRequestService
{
    public function __construct(
        private readonly ProcurementDocumentNumberAllocator $numbers,
        private readonly ProcurementApInvoiceOpenBalanceService $balances,
        private readonly ProcurementLifecycleAuditService $audit,
        private readonly ProcurementVendorRegistryService $vendors,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function createFromInvoice(ProcurementApInvoice $invoice, array $input, TenantUser $actor): ProcurementPaymentRequest
    {
        abort_if((string) $invoice->status !== ProcurementApInvoiceStatus::APPROVED, 422, __('Payment requests require an approved AP invoice.'));

        $amount = array_key_exists('amount', $input)
            ? round(max(0, (float) $input['amount']), 2)
            : $this->balances->openPayableForInvoice($invoice);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('Payment amount must be greater than zero.')],
            ]);
        }

        $open = $this->balances->openPayableForInvoice($invoice);
        if ($amount > $open + 0.01) {
            throw ValidationException::withMessages([
                'amount' => [__('Payment amount exceeds open payable balance (:open).', ['open' => number_format($open, 2)])],
            ]);
        }

        $vendorCode = $invoice->vendor_code ?? $invoice->purchaseOrder?->supplier;
        $vendorName = $invoice->vendor_name ?? $invoice->purchaseOrder?->vendor_name ?? $invoice->purchaseOrder?->supplier;
        $vendor = $vendorCode !== null ? $this->vendors->findByVendorCode((string) $vendorCode) : null;

        $request = ProcurementPaymentRequest::query()->create([
            'status' => ProcurementPaymentRequestStatus::DRAFT,
            'ap_invoice_id' => (string) $invoice->id,
            'vendor_code' => $vendorCode,
            'vendor_name' => $vendorName,
            'amount' => $amount,
            'currency_code' => $invoice->currency_code ?? 'PHP',
            'notes' => $input['notes'] ?? null,
            'requestor_id' => (string) $actor->id,
            'metadata_json' => $vendor !== null ? ['vendor_id' => (string) $vendor->id] : null,
        ]);

        $this->audit->record(
            ProcurementDocumentType::PAYMENT_REQUEST,
            (string) $request->id,
            null,
            'created',
            $actor,
            null,
            ['ap_invoice_id' => (string) $invoice->id, 'amount' => $amount],
        );

        return $request->refresh()->load(['apInvoice.purchaseOrder', 'requestor:id,name']);
    }

    public function submit(ProcurementPaymentRequest $request, TenantUser $actor): ProcurementPaymentRequest
    {
        abort_unless((string) $request->status === ProcurementPaymentRequestStatus::DRAFT, 422, __('Only draft payment requests can be submitted.'));

        return $this->transition($request, ProcurementPaymentRequestStatus::PENDING_APPROVAL, $actor, 'submitted');
    }

    public function approve(ProcurementPaymentRequest $request, TenantUser $actor): ProcurementPaymentRequest
    {
        abort_unless((string) $request->status === ProcurementPaymentRequestStatus::PENDING_APPROVAL, 422, __('Only pending payment requests can be approved.'));

        return DB::connection('tenant')->transaction(function () use ($request, $actor): ProcurementPaymentRequest {
            if ($request->document_no === null) {
                $request->document_no = $this->numbers->allocate(ProcurementDocumentType::PAYMENT_REQUEST);
            }
            $request->approved_by_id = (string) $actor->id;
            $request->approved_at = now();
            $request->status = ProcurementPaymentRequestStatus::APPROVED;
            $request->save();

            $this->audit->record(
                ProcurementDocumentType::PAYMENT_REQUEST,
                (string) $request->id,
                $request->document_no,
                'approved',
                $actor,
            );

            return $request->refresh()->load(['apInvoice.purchaseOrder', 'requestor:id,name']);
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function schedule(ProcurementPaymentRequest $request, array $input, TenantUser $actor): ProcurementPaymentRequest
    {
        abort_unless((string) $request->status === ProcurementPaymentRequestStatus::APPROVED, 422, __('Only approved payment requests can be scheduled.'));

        $scheduledDate = $input['scheduled_date'] ?? now()->addDays(3)->toDateString();

        return $this->transition($request, ProcurementPaymentRequestStatus::SCHEDULED, $actor, 'scheduled', [
            'scheduled_date' => $scheduledDate,
        ], static function (ProcurementPaymentRequest $model) use ($scheduledDate): void {
            $model->scheduled_date = $scheduledDate;
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function markPaid(ProcurementPaymentRequest $request, array $input, TenantUser $actor): ProcurementPaymentRequest
    {
        abort_unless((string) $request->status === ProcurementPaymentRequestStatus::SCHEDULED, 422, __('Only scheduled payment requests can be marked paid.'));

        $reference = trim((string) ($input['payment_reference'] ?? ''));

        return $this->transition($request, ProcurementPaymentRequestStatus::PAID, $actor, 'paid', [
            'payment_reference' => $reference !== '' ? $reference : null,
        ], static function (ProcurementPaymentRequest $model) use ($reference, $actor): void {
            $model->paid_at = now();
            $model->paid_by_id = (string) $actor->id;
            if ($reference !== '') {
                $model->payment_reference = $reference;
            }
        });
    }

    public function markReconciled(ProcurementPaymentRequest $request, TenantUser $actor): ProcurementPaymentRequest
    {
        abort_unless((string) $request->status === ProcurementPaymentRequestStatus::PAID, 422, __('Only paid payment requests can be reconciled.'));

        return $this->transition($request, ProcurementPaymentRequestStatus::RECONCILED, $actor, 'reconciled', [], static function (ProcurementPaymentRequest $model) use ($actor): void {
            $model->reconciled_at = now();
            $model->reconciled_by_id = (string) $actor->id;
        });
    }

    public function find(string $id): ?ProcurementPaymentRequest
    {
        return ProcurementPaymentRequest::query()
            ->with([
                'apInvoice.purchaseOrder',
                'paymentBatch',
                'requestor:id,name',
            ])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  callable(ProcurementPaymentRequest): void|null  $mutator
     */
    private function transition(
        ProcurementPaymentRequest $request,
        string $status,
        TenantUser $actor,
        string $action,
        array $metadata = [],
        ?callable $mutator = null,
    ): ProcurementPaymentRequest {
        return DB::connection('tenant')->transaction(function () use ($request, $status, $actor, $action, $metadata, $mutator): ProcurementPaymentRequest {
            $request->status = $status;
            if ($mutator !== null) {
                $mutator($request);
            }
            $request->save();

            $this->audit->record(
                ProcurementDocumentType::PAYMENT_REQUEST,
                (string) $request->id,
                $request->document_no,
                $action,
                $actor,
                null,
                $metadata,
            );

            return $request->refresh()->load(['apInvoice.purchaseOrder', 'requestor:id,name', 'paymentBatch']);
        });
    }
}
