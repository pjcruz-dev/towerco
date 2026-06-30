<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Models\ProcurementGrnLine;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Support\ProcurementDocumentType;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProcurementGrnService
{
    public function __construct(
        private readonly ProcurementGrnPoBalanceService $balances,
        private readonly ProcurementGrnReceiptPolicyService $receiptPolicy,
        private readonly ProcurementDocumentNumberAllocator $numbers,
        private readonly ProcurementDocumentEventDispatcher $events,
        private readonly ProcurementGrnRegistryService $registry,
        private readonly ProcurementGrnFileStorageService $files,
        private readonly ProcurementGrnMismatchService $mismatches,
        private readonly ProcurementInventoryStockService $inventoryStock,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array{grn: ProcurementGrn, warning: string|null}
     */
    public function createFromPurchaseOrder(ProcurementPo $po, array $input, TenantUser $actor, bool $post = false): array
    {
        $this->assertPoReceivable($po);

        return DB::connection('tenant')->transaction(function () use ($po, $input, $actor, $post): array {
            $context = $this->resolveSiteContext($po);
            $grn = ProcurementGrn::query()->create([
                'status' => ProcurementGrnStatus::DRAFT,
                'po_id' => (string) $po->id,
                'received_by_id' => (string) $actor->id,
                'project_id' => $input['project_id'] ?? $context['project_id'],
                'rollout_id' => $input['rollout_id'] ?? $context['rollout_id'],
                'site_id' => $input['site_id'] ?? $context['site_id'],
                'inventory_location_id' => $input['inventory_location_id'] ?? null,
                'gps_latitude' => $input['gps_latitude'] ?? null,
                'gps_longitude' => $input['gps_longitude'] ?? null,
                'gps_accuracy_meters' => $input['gps_accuracy_meters'] ?? null,
                'received_at' => $input['received_at'] ?? now(),
                'notes' => $input['notes'] ?? null,
                'metadata_json' => is_array($input['metadata'] ?? null) ? $input['metadata'] : null,
            ]);

            $warning = $this->syncLines($grn, $po, $input['lines'] ?? [], null);

            if ($post) {
                return $this->post($grn->refresh()->load(['lines', 'attachments', 'purchaseOrder.lines', 'receivedBy']), $actor, $warning);
            }

            return [
                'grn' => $this->registry->find((string) $grn->id) ?? $grn->refresh()->load(['lines', 'attachments', 'purchaseOrder', 'receivedBy']),
                'warning' => $warning,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function updateDraft(ProcurementGrn $grn, array $input, TenantUser $actor): ProcurementGrn
    {
        abort_unless(ProcurementGrnStatus::isEditable((string) $grn->status), 422, __('Only draft goods receipts can be edited.'));

        $po = $grn->purchaseOrder()->with('lines')->firstOrFail();

        return DB::connection('tenant')->transaction(function () use ($grn, $input, $po): ProcurementGrn {
            $grn->fill(array_filter([
                'project_id' => $input['project_id'] ?? null,
                'rollout_id' => $input['rollout_id'] ?? null,
                'site_id' => $input['site_id'] ?? null,
                'inventory_location_id' => $input['inventory_location_id'] ?? null,
                'gps_latitude' => $input['gps_latitude'] ?? null,
                'gps_longitude' => $input['gps_longitude'] ?? null,
                'gps_accuracy_meters' => $input['gps_accuracy_meters'] ?? null,
                'received_at' => $input['received_at'] ?? null,
                'notes' => $input['notes'] ?? null,
            ], static fn ($value) => $value !== null));

            $grn->save();

            if (array_key_exists('lines', $input)) {
                $this->syncLines($grn, $po, $input['lines'], (string) $grn->id);
            }

            return $this->registry->find((string) $grn->id) ?? $grn->refresh()->load(['lines', 'attachments', 'purchaseOrder', 'receivedBy']);
        });
    }

    /**
     * @return array{grn: ProcurementGrn, warning: string|null}
     */
    public function post(ProcurementGrn $grn, TenantUser $actor, ?string $existingWarning = null): array
    {
        abort_unless(ProcurementGrnStatus::isEditable((string) $grn->status), 422, __('Only draft goods receipts can be posted.'));

        $grn->loadMissing(['lines', 'purchaseOrder.lines']);
        $po = $grn->purchaseOrder;
        abort_if($po === null, 422, __('Purchase order not found for this goods receipt.'));
        $this->assertPoReceivable($po);

        $warning = $existingWarning;
        $hasReceipt = false;

        foreach ($grn->lines as $line) {
            $qty = (float) $line->quantity_received;
            if ($qty <= 0) {
                continue;
            }
            $hasReceipt = true;
            $poLine = $po->lines->firstWhere('id', $line->po_line_id);
            if ($poLine instanceof ProcurementPoLine) {
                $evaluation = $this->receiptPolicy->evaluateLineReceipt($poLine, $qty, (string) $grn->id);
                $warning = $warning ?? $evaluation['warning'];
            }
        }

        if (! $hasReceipt) {
            throw ValidationException::withMessages([
                'lines' => [__('At least one line must have a received quantity greater than zero.')],
            ]);
        }

        return DB::connection('tenant')->transaction(function () use ($grn, $po, $actor, $warning): array {
            $mismatchRows = $this->mismatches->analyze($grn, $po, (string) $grn->id);
            $grn->metadata_json = $this->mismatches->persistOnPost($grn, $warning, $mismatchRows);
            $grn->document_no = $this->numbers->allocateGoodsReceipt();
            $grn->status = ProcurementGrnStatus::POSTED;
            $grn->posted_at = now();
            $grn->received_at = $grn->received_at ?? now();
            $grn->save();

            $this->balances->refreshPurchaseOrderReceiptStatus($po->refresh()->load('lines'));

            $stockMovements = $this->inventoryStock->recordPostedGrn($grn->refresh()->load('lines'), $actor);
            if ($stockMovements !== []) {
                $metadata = is_array($grn->metadata_json) ? $grn->metadata_json : [];
                $metadata['inventory_movement_ids'] = array_column($stockMovements, 'id');
                $grn->metadata_json = $metadata;
                $grn->save();
            }

            $this->events->approved(
                ProcurementDocumentType::GOODS_RECEIPT,
                (string) $grn->id,
                $grn->document_no,
                (string) $actor->id,
                ['po_id' => $po->id],
            );

            return [
                'grn' => $this->registry->find((string) $grn->id) ?? $grn->refresh()->load(['lines', 'attachments', 'purchaseOrder', 'receivedBy']),
                'warning' => $warning,
            ];
        });
    }

    public function storeAttachment(ProcurementGrn $grn, UploadedFile $file, string $fieldName, TenantUser $actor): ProcurementGrn
    {
        abort_unless(ProcurementGrnStatus::isEditable((string) $grn->status), 422, __('Attachments can only be added to draft goods receipts.'));

        $this->files->store($grn, $file, $fieldName, $actor);

        return $this->registry->find((string) $grn->id) ?? $grn->refresh()->load(['lines', 'attachments', 'purchaseOrder', 'receivedBy']);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function syncLines(ProcurementGrn $grn, ProcurementPo $po, array $lines, ?string $excludeGrnId): ?string
    {
        $po->loadMissing('lines');
        $warning = null;

        if ($lines === []) {
            $lines = $po->lines->map(function (ProcurementPoLine $line): array {
                $remaining = $this->balances->remainingQuantityForPoLine($line);

                return [
                    'po_line_id' => (string) $line->id,
                    'quantity_received' => $remaining,
                ];
            })->filter(static fn (array $row) => (float) $row['quantity_received'] > 0)->values()->all();
        }

        ProcurementGrnLine::query()->where('grn_id', $grn->id)->delete();

        $order = 0;
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }

            $poLineId = (string) ($row['po_line_id'] ?? '');
            $poLine = $po->lines->firstWhere('id', $poLineId);
            if (! $poLine instanceof ProcurementPoLine) {
                continue;
            }

            $qty = (float) ($row['quantity_received'] ?? 0);
            if ($qty <= 0) {
                continue;
            }

            $evaluation = $this->receiptPolicy->evaluateLineReceipt($poLine, $qty, $excludeGrnId);
            $warning = $warning ?? $evaluation['warning'];

            ProcurementGrnLine::query()->create([
                'grn_id' => $grn->id,
                'po_line_id' => $poLine->id,
                'line_order' => $order++,
                'description' => $poLine->description,
                'uom' => $poLine->uom,
                'quantity_ordered' => (float) $poLine->quantity,
                'quantity_received' => $qty,
                'line_notes' => $row['line_notes'] ?? null,
            ]);
        }

        return $warning;
    }

    private function assertPoReceivable(ProcurementPo $po): void
    {
        if (! in_array((string) $po->status, [
            ProcurementPoStatus::APPROVED,
            ProcurementPoStatus::SENT,
            ProcurementPoStatus::PARTIALLY_RECEIVED,
        ], true)) {
            throw ValidationException::withMessages([
                'po' => [__('Goods can only be received against approved or in-transit purchase orders.')],
            ]);
        }
    }

    /**
     * @return array{project_id: string|null, rollout_id: string|null, site_id: string|null}
     */
    private function resolveSiteContext(ProcurementPo $po): array
    {
        $po->loadMissing('prLinks.purchaseRequisition');
        $pr = $po->prLinks->first()?->purchaseRequisition;

        return [
            'project_id' => $pr?->project_id,
            'rollout_id' => $pr?->rollout_id,
            'site_id' => $pr?->site_id,
        ];
    }
}
