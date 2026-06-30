<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ProcurementGrnRegistryService
{
    public function __construct(
        private readonly ProcurementGrnPoBalanceService $balances,
        private readonly ProcurementGrnMismatchService $mismatches,
        private readonly ProcurementInventoryStockService $inventoryStock,
        private readonly ProcurementInventoryLocationService $inventoryLocations,
    ) {}

    public function paginate(
        int $page,
        int $perPage,
        ?string $search = null,
        ?string $status = null,
        ?string $poId = null,
    ): LengthAwarePaginator {
        $query = ProcurementGrn::query()
            ->with(['purchaseOrder:id,document_no,supplier,vendor_name', 'receivedBy:id,name'])
            ->orderByDesc('updated_at');

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
                    ->orWhereHas('purchaseOrder', static fn ($pq) => $pq
                        ->where('document_no', 'like', $like)
                        ->orWhere('supplier', 'like', $like));
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    public function find(string $id): ?ProcurementGrn
    {
        return ProcurementGrn::query()
            ->with([
                'lines.poLine',
                'attachments',
                'purchaseOrder.lines',
                'purchaseOrder.requestor:id,name,email',
                'receivedBy:id,name,email',
                'inventoryLocation:id,code,name,location_kind',
            ])
            ->find($id);
    }

    /**
     * @return array<string, mixed>
     */
    public function toListPayload(ProcurementGrn $grn): array
    {
        return [
            'id' => (string) $grn->id,
            'document_no' => $grn->document_no,
            'status' => $grn->status,
            'status_label' => ProcurementGrnStatus::label((string) $grn->status),
            'po_id' => (string) $grn->po_id,
            'po_document_no' => $grn->purchaseOrder?->document_no,
            'po_supplier' => $grn->purchaseOrder?->supplier ?? $grn->purchaseOrder?->vendor_name,
            'received_by' => $grn->receivedBy ? [
                'id' => (string) $grn->receivedBy->id,
                'name' => $grn->receivedBy->name,
            ] : null,
            'received_at' => $grn->received_at?->toIso8601String(),
            'posted_at' => $grn->posted_at?->toIso8601String(),
            'updated_at' => $grn->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDetailPayload(ProcurementGrn $grn): array
    {
        $po = $grn->purchaseOrder;
        $receiptSummary = $po !== null ? $this->balances->lineReceiptSummary($po) : [];

        return $this->toListPayload($grn) + [
            'project_id' => $grn->project_id,
            'rollout_id' => $grn->rollout_id,
            'site_id' => $grn->site_id,
            'inventory_location_id' => $grn->inventory_location_id,
            'inventory_location' => $grn->inventoryLocation
                ? $this->inventoryLocations->asPayload($grn->inventoryLocation)
                : null,
            'stock_movements' => (string) $grn->status === ProcurementGrnStatus::POSTED
                ? $this->inventoryStock->movementsForGrn((string) $grn->id)
                : [],
            'gps_latitude' => $grn->gps_latitude !== null ? (float) $grn->gps_latitude : null,
            'gps_longitude' => $grn->gps_longitude !== null ? (float) $grn->gps_longitude : null,
            'gps_accuracy_meters' => $grn->gps_accuracy_meters !== null ? (float) $grn->gps_accuracy_meters : null,
            'notes' => $grn->notes,
            'receipt_warning' => is_array($grn->metadata_json) ? ($grn->metadata_json['receipt_warning'] ?? null) : null,
            'mismatches' => $this->mismatches->resolveForGrn($grn),
            'metadata' => $grn->metadata_json ?? [],
            'purchase_order' => $po ? [
                'id' => (string) $po->id,
                'document_no' => $po->document_no,
                'status' => $po->status,
                'supplier' => $po->supplier,
                'line_receipt_summary' => $receiptSummary,
            ] : null,
            'lines' => $grn->lines->map(static fn ($line) => [
                'id' => (string) $line->id,
                'line_order' => $line->line_order,
                'po_line_id' => (string) $line->po_line_id,
                'description' => $line->description,
                'uom' => $line->uom,
                'quantity_ordered' => (float) $line->quantity_ordered,
                'quantity_received' => (float) $line->quantity_received,
                'line_notes' => $line->line_notes,
            ])->values()->all(),
            'attachments' => $grn->attachments->map(static fn ($attachment) => [
                'id' => (string) $attachment->id,
                'field_name' => $attachment->field_name,
                'file_name' => $attachment->file_name,
                'mime_type' => $attachment->mime_type,
                'size_bytes' => $attachment->size_bytes,
            ])->values()->all(),
        ];
    }
}
