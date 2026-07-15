<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementCreditNote;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementCreditNoteStatus;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementCreditNoteService
{
    public function __construct(
        private readonly ProcurementDocumentNumberAllocator $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input, TenantUser $actor): ProcurementCreditNote
    {
        $poId = (string) ($input['po_id'] ?? '');
        abort_if($poId === '', 422, __('Purchase order is required.'));

        $apInvoiceId = $input['ap_invoice_id'] ?? null;
        if ($apInvoiceId !== null) {
            $invoice = ProcurementApInvoice::query()->find($apInvoiceId);
            abort_if($invoice === null || (string) $invoice->po_id !== $poId, 422, __('Credit note must reference an invoice on the same PO.'));
            abort_if((string) $invoice->status !== ProcurementApInvoiceStatus::APPROVED, 422, __('Credit notes can only be issued against approved AP invoices.'));
        }

        $amount = round(max(0, (float) ($input['amount'] ?? 0)), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => [__('Credit note amount must be greater than zero.')],
            ]);
        }

        return ProcurementCreditNote::query()->create([
            'status' => ProcurementCreditNoteStatus::DRAFT,
            'po_id' => $poId,
            'ap_invoice_id' => $apInvoiceId,
            'vendor_credit_note_no' => $input['vendor_credit_note_no'] ?? null,
            'credit_date' => $input['credit_date'] ?? now()->toDateString(),
            'amount' => $amount,
            'reason' => $input['reason'] ?? null,
            'created_by_id' => (string) $actor->id,
        ]);
    }

    public function approve(ProcurementCreditNote $note, TenantUser $actor): ProcurementCreditNote
    {
        abort_unless(ProcurementCreditNoteStatus::isEditable((string) $note->status), 422, __('Only draft credit notes can be approved.'));

        return DB::connection('tenant')->transaction(function () use ($note, $actor): ProcurementCreditNote {
            if ($note->document_no === null) {
                $note->document_no = $this->numbers->allocate(ProcurementDocumentType::CREDIT_NOTE);
            }
            $note->status = ProcurementCreditNoteStatus::APPROVED;
            $note->approved_at = now();
            $note->approved_by_id = (string) $actor->id;
            $note->save();

            return $note->refresh()->load(['purchaseOrder', 'apInvoice']);
        });
    }

    public function find(string $id): ?ProcurementCreditNote
    {
        return ProcurementCreditNote::query()
            ->with(['purchaseOrder', 'apInvoice', 'createdBy:id,name'])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function asPayload(ProcurementCreditNote $note): array
    {
        return [
            'id' => (string) $note->id,
            'document_no' => $note->document_no,
            'status' => $note->status,
            'status_label' => ProcurementCreditNoteStatus::label((string) $note->status),
            'po_id' => (string) $note->po_id,
            'po_document_no' => $note->purchaseOrder?->document_no,
            'ap_invoice_id' => $note->ap_invoice_id,
            'ap_invoice_document_no' => $note->apInvoice?->document_no,
            'vendor_credit_note_no' => $note->vendor_credit_note_no,
            'credit_date' => $note->credit_date?->format('Y-m-d'),
            'amount' => (float) $note->amount,
            'reason' => $note->reason,
            'approved_at' => $note->approved_at?->toIso8601String(),
            'created_by' => $note->createdBy ? [
                'id' => (string) $note->createdBy->id,
                'name' => $note->createdBy->name,
            ] : null,
        ];
    }
}
