<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementGrnLine;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoLine;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;

final class ProcurementGrnPoBalanceService
{
    public function receivedQuantityForPoLine(string $poLineId, ?string $excludeGrnId = null): float
    {
        $query = ProcurementGrnLine::query()
            ->where('po_line_id', $poLineId)
            ->whereHas('grn', static function ($q) use ($excludeGrnId): void {
                $q->where('status', ProcurementGrnStatus::POSTED);
                if ($excludeGrnId !== null) {
                    $q->where('id', '<>', $excludeGrnId);
                }
            });

        return (float) $query->sum('quantity_received');
    }

    public function remainingQuantityForPoLine(ProcurementPoLine $poLine, ?string $excludeGrnId = null): float
    {
        $ordered = (float) $poLine->quantity;
        $received = $this->receivedQuantityForPoLine((string) $poLine->id, $excludeGrnId);

        return max(0, round($ordered - $received, 4));
    }

    public function isPoFullyReceived(ProcurementPo $po): bool
    {
        $po->loadMissing('lines');
        foreach ($po->lines as $line) {
            if ($this->remainingQuantityForPoLine($line) > 0.0001) {
                return false;
            }
        }

        return $po->lines->isNotEmpty();
    }

    public function isPoPartiallyReceived(ProcurementPo $po): bool
    {
        $po->loadMissing('lines');
        $anyReceived = false;
        $allReceived = true;

        foreach ($po->lines as $line) {
            $received = $this->receivedQuantityForPoLine((string) $line->id);
            if ($received > 0.0001) {
                $anyReceived = true;
            }
            if ($this->remainingQuantityForPoLine($line) > 0.0001) {
                $allReceived = false;
            }
        }

        return $anyReceived && ! $allReceived;
    }

    public function refreshPurchaseOrderReceiptStatus(ProcurementPo $po): ProcurementPo
    {
        if (! in_array((string) $po->status, [
            ProcurementPoStatus::APPROVED,
            ProcurementPoStatus::SENT,
            ProcurementPoStatus::PARTIALLY_RECEIVED,
            ProcurementPoStatus::RECEIVED,
        ], true)) {
            return $po;
        }

        if ($this->isPoFullyReceived($po)) {
            $po->status = ProcurementPoStatus::RECEIVED;
        } elseif ($this->isPoPartiallyReceived($po)) {
            $po->status = ProcurementPoStatus::PARTIALLY_RECEIVED;
        }

        $po->save();

        return $po->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function lineReceiptSummary(ProcurementPo $po): array
    {
        $po->loadMissing('lines');

        return $po->lines->map(function (ProcurementPoLine $line): array {
            $received = $this->receivedQuantityForPoLine((string) $line->id);

            return [
                'po_line_id' => (string) $line->id,
                'description' => $line->description,
                'quantity_ordered' => (float) $line->quantity,
                'quantity_received' => $received,
                'quantity_remaining' => $this->remainingQuantityForPoLine($line),
            ];
        })->values()->all();
    }
}
