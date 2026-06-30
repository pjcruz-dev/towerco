<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementApInvoiceLine;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;

final class ProcurementApInvoicePoBalanceService
{
    public function invoicedQuantityForPoLine(string $poLineId, ?string $excludeInvoiceId = null): float
    {
        $query = ProcurementApInvoiceLine::query()
            ->where('po_line_id', $poLineId)
            ->whereHas('apInvoice', static function ($q) use ($excludeInvoiceId): void {
                $q->whereIn('status', [
                    ProcurementApInvoiceStatus::PENDING_APPROVAL,
                    ProcurementApInvoiceStatus::APPROVED,
                ]);
                if ($excludeInvoiceId !== null) {
                    $q->where('id', '<>', $excludeInvoiceId);
                }
            });

        return (float) $query->sum('quantity_invoiced');
    }

    public function remainingQuantityForPoLine(string $poLineId, float $orderedQuantity, ?string $excludeInvoiceId = null): float
    {
        $invoiced = $this->invoicedQuantityForPoLine($poLineId, $excludeInvoiceId);

        return max(0, round($orderedQuantity - $invoiced, 4));
    }

    public function invoicedAmountForPo(string $poId, ?string $excludeInvoiceId = null): float
    {
        $query = ProcurementApInvoice::query()
            ->where('po_id', $poId)
            ->whereIn('status', [
                ProcurementApInvoiceStatus::PENDING_APPROVAL,
                ProcurementApInvoiceStatus::APPROVED,
            ]);

        if ($excludeInvoiceId !== null) {
            $query->where('id', '<>', $excludeInvoiceId);
        }

        return round((float) $query->sum('grand_total'), 2);
    }
}
