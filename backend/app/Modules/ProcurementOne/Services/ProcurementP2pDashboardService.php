<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class ProcurementP2pDashboardService
{
    /**
     * @return array{
     *   kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   cycle_times: list<array{key: string, label: string, value: int|string, unit: string}>,
     *   open_pr_count: int,
     *   po_outstanding_amount: float,
     *   po_outstanding_count: int,
     *   grn_pending_count: int
     * }
     */
    public function snapshot(): array
    {
        $openPrCount = (int) ProcurementPr::query()
            ->whereIn('status', [ProcurementPrStatus::APPROVED, ProcurementPrStatus::CONVERTED])
            ->count();

        $openPoQuery = ProcurementPo::query()->whereIn('status', [
            ProcurementPoStatus::APPROVED,
            ProcurementPoStatus::SENT,
            ProcurementPoStatus::PARTIALLY_RECEIVED,
        ]);

        $poOutstandingAmount = (float) $openPoQuery->sum('grand_total');
        $poOutstandingCount = (int) (clone $openPoQuery)->count();

        $grnPendingCount = (int) ProcurementGrn::query()
            ->where('status', ProcurementGrnStatus::DRAFT)
            ->count();

        return [
            'kpis' => [
                ['key' => 'open_pr', 'label' => 'Open PRs', 'value' => $openPrCount, 'tone' => $openPrCount > 0 ? 'warning' : 'success'],
                ['key' => 'po_outstanding', 'label' => 'PO outstanding', 'value' => number_format($poOutstandingAmount, 2, '.', ''), 'tone' => 'neutral'],
                ['key' => 'open_po_count', 'label' => 'Open POs', 'value' => $poOutstandingCount, 'tone' => 'neutral'],
                ['key' => 'grn_pending', 'label' => 'GRN pending', 'value' => $grnPendingCount, 'tone' => $grnPendingCount > 0 ? 'warning' : 'success'],
            ],
            'cycle_times' => [
                [
                    'key' => 'pr_submit_to_approve_days',
                    'label' => 'PR submit → approve',
                    'value' => $this->averagePrSubmitToApproveDays(),
                    'unit' => 'days',
                ],
                [
                    'key' => 'pr_approve_to_po_days',
                    'label' => 'PR approve → PO create',
                    'value' => $this->averagePrApproveToPoDays(),
                    'unit' => 'days',
                ],
                [
                    'key' => 'po_approve_to_grn_days',
                    'label' => 'PO approve → GRN post',
                    'value' => $this->averagePoApproveToGrnDays(),
                    'unit' => 'days',
                ],
            ],
            'open_pr_count' => $openPrCount,
            'po_outstanding_amount' => round($poOutstandingAmount, 2),
            'po_outstanding_count' => $poOutstandingCount,
            'grn_pending_count' => $grnPendingCount,
        ];
    }

    private function averagePrSubmitToApproveDays(): float
    {
        $driver = DB::connection('tenant')->getDriverName();
        if ($driver === 'sqlite') {
            $avgHours = ProcurementPr::query()
                ->whereNotNull('submitted_at')
                ->whereNotNull('approved_at')
                ->where('approved_at', '>=', Carbon::now()->subDays(180))
                ->selectRaw('AVG((julianday(approved_at) - julianday(submitted_at)) * 24) as avg_hours')
                ->value('avg_hours');
        } else {
            $avgHours = ProcurementPr::query()
                ->whereNotNull('submitted_at')
                ->whereNotNull('approved_at')
                ->where('approved_at', '>=', Carbon::now()->subDays(180))
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, submitted_at, approved_at)) as avg_hours')
                ->value('avg_hours');
        }

        if ($avgHours === null) {
            return 0.0;
        }

        return round(((float) $avgHours) / 24, 1);
    }

    private function averagePrApproveToPoDays(): float
    {
        $driver = DB::connection('tenant')->getDriverName();
        if ($driver === 'sqlite') {
            $avgHours = DB::connection('tenant')->table('procurement_po_pr_links as link')
                ->join('procurement_prs as pr', 'pr.id', '=', 'link.pr_id')
                ->join('procurement_pos as po', 'po.id', '=', 'link.po_id')
                ->whereNotNull('pr.approved_at')
                ->where('po.created_at', '>=', Carbon::now()->subDays(180))
                ->selectRaw('AVG((julianday(po.created_at) - julianday(pr.approved_at)) * 24) as avg_hours')
                ->value('avg_hours');
        } else {
            $avgHours = DB::connection('tenant')->table('procurement_po_pr_links as link')
                ->join('procurement_prs as pr', 'pr.id', '=', 'link.pr_id')
                ->join('procurement_pos as po', 'po.id', '=', 'link.po_id')
                ->whereNotNull('pr.approved_at')
                ->where('po.created_at', '>=', Carbon::now()->subDays(180))
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, pr.approved_at, po.created_at)) as avg_hours')
                ->value('avg_hours');
        }

        if ($avgHours === null) {
            return 0.0;
        }

        return round(((float) $avgHours) / 24, 1);
    }

    private function averagePoApproveToGrnDays(): float
    {
        $driver = DB::connection('tenant')->getDriverName();
        if ($driver === 'sqlite') {
            $avgHours = DB::connection('tenant')->table('procurement_grns as grn')
                ->join('procurement_pos as po', 'po.id', '=', 'grn.po_id')
                ->whereNotNull('po.approved_at')
                ->where('grn.status', ProcurementGrnStatus::POSTED)
                ->where('grn.posted_at', '>=', Carbon::now()->subDays(180))
                ->selectRaw('AVG((julianday(grn.posted_at) - julianday(po.approved_at)) * 24) as avg_hours')
                ->value('avg_hours');
        } else {
            $avgHours = DB::connection('tenant')->table('procurement_grns as grn')
                ->join('procurement_pos as po', 'po.id', '=', 'grn.po_id')
                ->whereNotNull('po.approved_at')
                ->where('grn.status', ProcurementGrnStatus::POSTED)
                ->where('grn.posted_at', '>=', Carbon::now()->subDays(180))
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, po.approved_at, grn.posted_at)) as avg_hours')
                ->value('avg_hours');
        }

        if ($avgHours === null) {
            return 0.0;
        }

        return round(((float) $avgHours) / 24, 1);
    }
}
