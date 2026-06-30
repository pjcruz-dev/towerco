<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Models\EApprovalSubmission;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementPoRegistryService
{
    public function __construct(
        private readonly ProcurementLifecycleAuditService $lifecycleAudit,
        private readonly ProcurementGrnPoBalanceService $grnBalances,
        private readonly ProcurementContractSpendService $contractSpend,
        private readonly ProcurementComposeValuesResolver $composeValues,
        private readonly ProcurementPoPrintEnrichmentService $poAmounts,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $requestorId = null,
        ?string $prId = null,
    ): LengthAwarePaginator {
        $query = ProcurementPo::query()
            ->with(['requestor:id,name,email'])
            ->orderByDesc('updated_at');

        if ($status !== null && $status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($requestorId !== null && $requestorId !== '') {
            $query->where('requestor_id', $requestorId);
        }

        if ($prId !== null && $prId !== '') {
            $query->whereHas('prLinks', static fn ($q) => $q->where('pr_id', $prId));
        }

        if ($search !== null && trim($search) !== '') {
            $like = '%'.addcslashes(trim($search), '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('document_no', 'like', $like)
                    ->orWhere('vendor_name', 'like', $like)
                    ->orWhere('supplier', 'like', $like);
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $id): ?ProcurementPo
    {
        return ProcurementPo::query()
            ->with(['lines', 'prLinks.purchaseRequisition', 'requestor:id,name,email', 'contract.vendor:id,vendor_code,company_name'])
            ->withCount('goodsReceipts')
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementPo $po): array
    {
        return [
            'id' => (string) $po->id,
            'document_no' => $po->document_no,
            'status' => $po->status,
            'status_label' => ProcurementPoStatus::label((string) $po->status),
            'vendor_code' => $po->vendor_code,
            'vendor_name' => $po->vendor_name,
            'grand_total' => (float) $this->poAmounts->displayGrandTotal($po),
            'currency_code' => $po->currency_code,
            'requestor' => $po->requestor ? [
                'id' => (string) $po->requestor->id,
                'name' => $po->requestor->name,
            ] : null,
            'submitted_at' => $po->submitted_at?->toIso8601String(),
            'approved_at' => $po->approved_at?->toIso8601String(),
            'updated_at' => $po->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementPo $po): array
    {
        return $this->toListPayload($po) + [
            'supplier' => $po->supplier,
            'ship_to' => $po->ship_to,
            'delivery_date' => $po->delivery_date?->format('Y-m-d'),
            'payment_terms' => $po->payment_terms,
            'exchange_rate' => (float) $po->exchange_rate,
            'delivery_location' => $po->delivery_location,
            'vatable_amount' => (float) $po->vatable_amount,
            'vat_exempt_amount' => (float) $po->vat_exempt_amount,
            'zero_rated_amount' => (float) $po->zero_rated_amount,
            'vat_rate' => (float) $po->vat_rate,
            'vat_amount' => (float) $po->vat_amount,
            'total_vat_inclusive' => (float) $po->total_vat_inclusive,
            'less_discount' => (float) $po->less_discount,
            'total_amount' => (float) $po->total_amount,
            'e_approval_submission_id' => $po->e_approval_submission_id,
            'parent_submission_id' => $this->parentSubmissionId($po),
            'e_approval_form_id' => $po->e_approval_form_id,
            'compose_values' => $this->composeValues->forPurchaseOrder($po),
            'sent_at' => $po->sent_at?->toIso8601String(),
            'cancelled_at' => $po->cancelled_at?->toIso8601String(),
            'voided_at' => $po->voided_at?->toIso8601String(),
            'void_reason' => $po->void_reason,
            'lifecycle_reason' => $po->lifecycle_reason,
            'lifecycle_events' => $this->lifecycleAudit->listForDocument(
                ProcurementDocumentType::PURCHASE_ORDER,
                (string) $po->id,
            ),
            'metadata' => $po->metadata_json ?? [],
            'purchase_requisitions' => $po->prLinks->map(static fn ($link) => [
                'id' => (string) $link->pr_id,
                'document_no' => $link->purchaseRequisition?->document_no,
                'title' => $link->purchaseRequisition?->title,
                'allocated_amount' => (float) $link->allocated_amount,
                'e_approval_submission_id' => $link->purchaseRequisition?->e_approval_submission_id,
            ])->values()->all(),
            'lines' => $po->lines->map(static fn ($line) => [
                'id' => (string) $line->id,
                'line_order' => $line->line_order,
                'item' => $line->item,
                'description' => $line->description,
                'uom' => $line->uom,
                'quantity' => (float) $line->quantity,
                'unit_price' => (float) $line->unit_price,
                'discount' => (float) $line->discount,
                'amount' => (float) $line->amount,
                'pr_id' => $line->pr_id,
                'pr_line_id' => $line->pr_line_id,
            ])->values()->all(),
            'line_receipt_summary' => $this->grnBalances->lineReceiptSummary($po),
            'goods_receipt_count' => (int) ($po->goods_receipts_count ?? $po->goodsReceipts()->count()),
            'contract_id' => $po->contract_id,
            'contract' => $po->contract ? [
                'id' => (string) $po->contract->id,
                'document_no' => $po->contract->document_no,
                'title' => $po->contract->title,
                'status' => $po->contract->status,
                'spend_ceiling' => $po->contract->spend_ceiling !== null ? (float) $po->contract->spend_ceiling : null,
                'committed_po_amount' => (float) $po->contract->committed_po_amount,
                'available_spend' => $this->contractSpend->openCeiling($po->contract, (string) $po->id),
                'vendor' => $po->contract->vendor ? [
                    'id' => (string) $po->contract->vendor->id,
                    'vendor_code' => $po->contract->vendor->vendor_code,
                    'company_name' => $po->contract->vendor->company_name,
                ] : null,
            ] : null,
        ];
    }

    private function parentSubmissionId(ProcurementPo $po): ?string
    {
        if ($po->e_approval_submission_id !== null && $po->e_approval_submission_id !== '') {
            $parentId = EApprovalSubmission::query()
                ->where('id', $po->e_approval_submission_id)
                ->value('parent_submission_id');

            if ($parentId !== null && $parentId !== '') {
                return (string) $parentId;
            }
        }

        $linkedPr = $po->prLinks->first()?->purchaseRequisition;
        if ($linkedPr?->e_approval_submission_id !== null && $linkedPr->e_approval_submission_id !== '') {
            return (string) $linkedPr->e_approval_submission_id;
        }

        return null;
    }
}
