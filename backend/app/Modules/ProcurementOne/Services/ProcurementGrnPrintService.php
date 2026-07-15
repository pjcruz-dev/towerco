<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use App\Modules\Tenancy\Support\TenantAppUrlResolver;

final class ProcurementGrnPrintService
{
    public function __construct(
        private readonly ProcurementGrnMismatchService $mismatches,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(ProcurementGrn $grn): array
    {
        abort_unless((string) $grn->status === ProcurementGrnStatus::POSTED, 422, __('Only posted goods receipts can be printed.'));

        $grn->loadMissing([
            'lines',
            'attachments',
            'purchaseOrder.requestor',
            'receivedBy:id,name,email',
        ]);

        $po = $grn->purchaseOrder;
        $brand = app(TenantAppUrlResolver::class)->mailBrandLabel();

        return [
            'brand' => $brand,
            'document_no' => $grn->document_no,
            'status' => $grn->status,
            'status_label' => ProcurementGrnStatus::label((string) $grn->status),
            'po_id' => (string) $grn->po_id,
            'po_document_no' => $po?->document_no,
            'supplier' => $po?->supplier ?? $po?->vendor_name,
            'received_by' => $grn->receivedBy ? [
                'id' => (string) $grn->receivedBy->id,
                'name' => $grn->receivedBy->name,
                'email' => $grn->receivedBy->email,
            ] : null,
            'received_at' => $grn->received_at?->toIso8601String(),
            'posted_at' => $grn->posted_at?->toIso8601String(),
            'project_id' => $grn->project_id,
            'rollout_id' => $grn->rollout_id,
            'site_id' => $grn->site_id,
            'gps_latitude' => $grn->gps_latitude !== null ? (float) $grn->gps_latitude : null,
            'gps_longitude' => $grn->gps_longitude !== null ? (float) $grn->gps_longitude : null,
            'gps_accuracy_meters' => $grn->gps_accuracy_meters !== null ? (float) $grn->gps_accuracy_meters : null,
            'notes' => $grn->notes,
            'receipt_warning' => is_array($grn->metadata_json) ? ($grn->metadata_json['receipt_warning'] ?? null) : null,
            'mismatches' => $this->mismatches->resolveForGrn($grn),
            'lines' => $grn->lines->map(static fn ($line) => [
                'description' => $line->description,
                'uom' => $line->uom,
                'quantity_ordered' => (float) $line->quantity_ordered,
                'quantity_received' => (float) $line->quantity_received,
                'line_notes' => $line->line_notes,
            ])->values()->all(),
            'attachments' => $grn->attachments->map(static fn ($attachment) => [
                'file_name' => $attachment->file_name,
                'field_name' => $attachment->field_name,
            ])->values()->all(),
            'printed_at' => now()->toIso8601String(),
        ];
    }
}
