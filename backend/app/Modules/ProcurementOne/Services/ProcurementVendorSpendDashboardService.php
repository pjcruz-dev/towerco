<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;

final class ProcurementVendorSpendDashboardService
{
    /**
     * @param  array{from?: string|null, to?: string|null, period?: string|null}  $input
     * @return array{
     *   period_label: string,
     *   total_spend: float,
     *   vendor_count: int,
     *   rows: list<array{vendor_code: string|null, vendor_name: string|null, po_count: int, total_spend: float, currency_code: string|null}>
     * }
     */
    public function snapshot(array $input, ProcurementExportDateRangeService $dateRange): array
    {
        $range = $dateRange->resolve($input);

        $rows = ProcurementPo::query()
            ->selectRaw('vendor_code, vendor_name, currency_code, COUNT(*) as po_count, SUM(grand_total) as total_spend')
            ->whereIn('status', [
                ProcurementPoStatus::APPROVED,
                ProcurementPoStatus::SENT,
                ProcurementPoStatus::PARTIALLY_RECEIVED,
                ProcurementPoStatus::RECEIVED,
                ProcurementPoStatus::CLOSED,
            ])
            ->where(function ($query) use ($range): void {
                $query->whereBetween('approved_at', [$range['from'], $range['to']])
                    ->orWhereBetween('created_at', [$range['from'], $range['to']]);
            })
            ->groupBy('vendor_code', 'vendor_name', 'currency_code')
            ->orderByDesc('total_spend')
            ->limit(25)
            ->get()
            ->map(static fn ($row) => [
                'vendor_code' => $row->vendor_code,
                'vendor_name' => $row->vendor_name,
                'po_count' => (int) $row->po_count,
                'total_spend' => round((float) $row->total_spend, 2),
                'currency_code' => $row->currency_code,
            ])
            ->values()
            ->all();

        $totalSpend = round(array_sum(array_column($rows, 'total_spend')), 2);

        return [
            'period_label' => $range['label'],
            'total_spend' => $totalSpend,
            'vendor_count' => count($rows),
            'rows' => $rows,
        ];
    }
}
