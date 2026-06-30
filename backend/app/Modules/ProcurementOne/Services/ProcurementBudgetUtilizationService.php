<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Models\SiteProfitabilityRecord;
use Illuminate\Support\Facades\DB;

final class ProcurementBudgetUtilizationService
{
    public function __construct(
        private readonly ProcurementPoPrBalanceService $poBalances,
    ) {}

    /**
     * @return array{
     *   budget_total: float|null,
     *   committed: float,
     *   committed_pr: float,
     *   committed_po: float,
     *   available: float|null,
     *   utilization_percent: float|null,
     *   source: string
     * }
     */
    public function snapshotForRollout(?string $rolloutId): array
    {
        if ($rolloutId === null || $rolloutId === '') {
            return $this->emptySnapshot();
        }

        $budgetTotal = $this->resolveBudgetTotalForRollout($rolloutId);
        $committed = $this->sumCommittedForRollout($rolloutId);
        $breakdown = $this->committedBreakdownForRollout($rolloutId);

        return $this->buildSnapshot($budgetTotal, $committed, $breakdown, 'rollout');
    }

    /**
     * @return array{
     *   budget_total: float|null,
     *   committed: float,
     *   committed_pr: float,
     *   committed_po: float,
     *   available: float|null,
     *   utilization_percent: float|null,
     *   source: string
     * }
     */
    public function snapshotForProject(?string $projectId): array
    {
        if ($projectId === null || $projectId === '') {
            return $this->emptySnapshot();
        }

        $rolloutIds = RolloutProgram::query()->where('project_id', $projectId)->pluck('id');
        if ($rolloutIds->isEmpty()) {
            return $this->emptySnapshot();
        }

        $budgetTotal = 0.0;
        $hasBudget = false;
        $budgetTotals = $this->resolveBudgetTotalsForRollouts(
            $rolloutIds->map(static fn ($id): string => (string) $id)->all(),
        );
        foreach ($budgetTotals as $rolloutBudget) {
            if ($rolloutBudget !== null) {
                $hasBudget = true;
                $budgetTotal += $rolloutBudget;
            }
        }

        $projectBudgetLines = ProcurementBudgetLineService::sumActiveBudgetForProject($projectId);
        if ($projectBudgetLines > 0) {
            $hasBudget = true;
            $budgetTotal = $projectBudgetLines;
        }

        $committed = 0.0;
        $committedPr = 0.0;
        $committedPo = 0.0;
        $rolloutBreakdowns = $this->committedBreakdownsForRollouts(
            $rolloutIds->map(static fn ($id): string => (string) $id)->all(),
        );
        foreach ($rolloutBreakdowns as $breakdown) {
            $committed += $breakdown['total'];
            $committedPr += $breakdown['pr'];
            $committedPo += $breakdown['po'];
        }

        $projectOnlyCommitted = $this->committedBreakdownForProjectOnly($projectId, $rolloutIds->all());
        $committed += $projectOnlyCommitted['total'];
        $committedPr += $projectOnlyCommitted['pr'];
        $committedPo += $projectOnlyCommitted['po'];

        return $this->buildSnapshot($hasBudget ? round($budgetTotal, 2) : null, round($committed, 2), [
            'pr' => round($committedPr, 2),
            'po' => round($committedPo, 2),
            'total' => round($committed, 2),
        ], 'project');
    }

    /**
     * Rollouts referenced by purchase requisitions with aggregated budget KPIs.
     *
     * @return array{budget_total: float|null, committed: float, available: float|null, utilization_percent: float|null}
     */
    public function aggregateSummaryForTrackedRollouts(): array
    {
        $rolloutIds = ProcurementPr::query()
            ->whereNotNull('rollout_id')
            ->distinct()
            ->pluck('rollout_id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        return $this->aggregateSummaryForRollouts($rolloutIds);
    }

    /**
     * @param  list<string>  $rolloutIds
     * @return array{budget_total: float|null, committed: float, available: float|null, utilization_percent: float|null}
     */
    public function aggregateSummaryForRollouts(array $rolloutIds): array
    {
        $rolloutIds = array_values(array_unique(array_filter($rolloutIds, static fn (string $id): bool => $id !== '')));
        if ($rolloutIds === []) {
            return [
                'budget_total' => null,
                'committed' => 0.0,
                'available' => null,
                'utilization_percent' => null,
            ];
        }

        $budgetTotals = $this->resolveBudgetTotalsForRollouts($rolloutIds);
        $breakdowns = $this->committedBreakdownsForRollouts($rolloutIds);

        $budgetTotal = 0.0;
        $committed = 0.0;
        $hasBudget = false;

        foreach ($rolloutIds as $rolloutId) {
            $rolloutBudget = $budgetTotals[$rolloutId] ?? null;
            if ($rolloutBudget !== null) {
                $hasBudget = true;
                $budgetTotal += $rolloutBudget;
            }

            $committed += $breakdowns[$rolloutId]['total'] ?? 0.0;
        }

        if (! $hasBudget) {
            return [
                'budget_total' => null,
                'committed' => round($committed, 2),
                'available' => null,
                'utilization_percent' => null,
            ];
        }

        $budgetTotal = round($budgetTotal, 2);
        $committed = round($committed, 2);
        $available = max(0, round($budgetTotal - $committed, 2));

        return [
            'budget_total' => $budgetTotal,
            'committed' => $committed,
            'available' => $available,
            'utilization_percent' => $budgetTotal > 0 ? round(($committed / $budgetTotal) * 100, 1) : null,
        ];
    }

    /**
     * @return array{pr: float, po: float, total: float}
     */
    public function committedBreakdownForRollout(string $rolloutId): array
    {
        return $this->committedBreakdownsForRollouts([$rolloutId])[$rolloutId] ?? [
            'pr' => 0.0,
            'po' => 0.0,
            'total' => 0.0,
        ];
    }

    /**
     * @param  list<string>  $rolloutIds
     * @return array<string, array{pr: float, po: float, total: float}>
     */
    public function committedBreakdownsForRollouts(array $rolloutIds): array
    {
        $rolloutIds = array_values(array_unique(array_filter($rolloutIds, static fn (string $id): bool => $id !== '')));
        $breakdowns = [];

        foreach ($rolloutIds as $rolloutId) {
            $breakdowns[$rolloutId] = [
                'pr' => 0.0,
                'po' => 0.0,
                'total' => 0.0,
            ];
        }

        if ($rolloutIds === []) {
            return $breakdowns;
        }

        $activePrTotals = ProcurementPr::query()
            ->whereIn('rollout_id', $rolloutIds)
            ->whereIn('status', [ProcurementPrStatus::PENDING_APPROVAL, ProcurementPrStatus::APPROVED])
            ->groupBy('rollout_id')
            ->selectRaw('rollout_id, sum(estimated_total) as aggregate')
            ->pluck('aggregate', 'rollout_id');

        foreach ($activePrTotals as $rolloutId => $total) {
            $rolloutKey = (string) $rolloutId;
            $amount = round((float) $total, 2);
            $breakdowns[$rolloutKey]['pr'] += $amount;
            $breakdowns[$rolloutKey]['total'] += $amount;
        }

        $convertedPrs = ProcurementPr::query()
            ->whereIn('rollout_id', $rolloutIds)
            ->where('status', ProcurementPrStatus::CONVERTED)
            ->get(['id', 'rollout_id']);

        if ($convertedPrs->isNotEmpty()) {
            $openByPr = $this->poBalances->openBalancesForPrIds(
                $convertedPrs->pluck('id')->map(static fn ($id): string => (string) $id)->all(),
            );

            foreach ($convertedPrs as $pr) {
                $rolloutKey = (string) $pr->rollout_id;
                $remainder = $openByPr[(string) $pr->id] ?? 0.0;
                $breakdowns[$rolloutKey]['pr'] = round($breakdowns[$rolloutKey]['pr'] + $remainder, 2);
                $breakdowns[$rolloutKey]['total'] = round($breakdowns[$rolloutKey]['total'] + $remainder, 2);
            }
        }

        foreach ($this->committedPoTotalsByRollout($rolloutIds) as $rolloutId => $poTotal) {
            $breakdowns[$rolloutId]['po'] = $poTotal;
            $breakdowns[$rolloutId]['total'] = round($breakdowns[$rolloutId]['total'] + $poTotal, 2);
        }

        return $breakdowns;
    }

    /**
     * @param  list<string>  $rolloutIds
     * @return array<string, float|null>
     */
    public function resolveBudgetTotalsForRollouts(array $rolloutIds): array
    {
        $rolloutIds = array_values(array_unique(array_filter($rolloutIds, static fn (string $id): bool => $id !== '')));
        $totals = [];

        foreach ($rolloutIds as $rolloutId) {
            $totals[$rolloutId] = null;
        }

        if ($rolloutIds === []) {
            return $totals;
        }

        $lineTotals = ProcurementBudgetLineService::sumActiveBudgetForRollouts($rolloutIds);
        $needsProfitability = [];

        foreach ($rolloutIds as $rolloutId) {
            $lineTotal = $lineTotals[$rolloutId] ?? 0.0;
            if ($lineTotal > 0) {
                $totals[$rolloutId] = $lineTotal;

                continue;
            }

            $needsProfitability[] = $rolloutId;
        }

        if ($needsProfitability === []) {
            return $totals;
        }

        $records = SiteProfitabilityRecord::query()
            ->whereIn('rollout_program_id', $needsProfitability)
            ->get(['rollout_program_id', 'baseline']);

        foreach ($records as $record) {
            $rolloutId = (string) $record->rollout_program_id;
            $totals[$rolloutId] = $this->sumBaselineBuckets($record->baseline ?? []);
        }

        return $totals;
    }

    public function resolveBudgetTotalForRollout(string $rolloutId): ?float
    {
        return $this->resolveBudgetTotalsForRollouts([$rolloutId])[$rolloutId] ?? null;
    }

    public function sumCommittedForRollout(string $rolloutId, ?string $excludePrId = null): float
    {
        $breakdown = $this->committedBreakdownForRollout($rolloutId);

        if ($excludePrId === null) {
            return $breakdown['total'];
        }

        $pr = ProcurementPr::query()->find($excludePrId);
        if ($pr === null || (string) $pr->rollout_id !== $rolloutId) {
            return $breakdown['total'];
        }

        if (in_array((string) $pr->status, [ProcurementPrStatus::PENDING_APPROVAL, ProcurementPrStatus::APPROVED], true)) {
            return max(0, round($breakdown['total'] - (float) $pr->estimated_total, 2));
        }

        if ((string) $pr->status === ProcurementPrStatus::CONVERTED) {
            return max(0, round($breakdown['total'] - $this->poBalances->openBalanceForPr($pr), 2));
        }

        return $breakdown['total'];
    }

    /**
     * @param  list<string>  $rolloutIds
     * @return array{pr: float, po: float, total: float}
     */
    private function committedBreakdownForProjectOnly(string $projectId, array $rolloutIds): array
    {
        $committedPr = (float) ProcurementPr::query()
            ->where('project_id', $projectId)
            ->whereNull('rollout_id')
            ->whereIn('status', [ProcurementPrStatus::PENDING_APPROVAL, ProcurementPrStatus::APPROVED])
            ->sum('estimated_total');

        $convertedRemainder = 0.0;
        $convertedPrs = ProcurementPr::query()
            ->where('project_id', $projectId)
            ->whereNull('rollout_id')
            ->where('status', ProcurementPrStatus::CONVERTED)
            ->get();
        foreach ($convertedPrs as $pr) {
            $convertedRemainder += $this->poBalances->openBalanceForPr($pr);
        }

        $committedPo = (float) ProcurementPo::query()
            ->whereIn('status', [
                ProcurementPoStatus::APPROVED,
                ProcurementPoStatus::SENT,
                ProcurementPoStatus::PARTIALLY_RECEIVED,
            ])
            ->whereHas('prLinks.purchaseRequisition', static function ($q) use ($projectId): void {
                $q->where('project_id', $projectId)->whereNull('rollout_id');
            })
            ->sum('grand_total');

        return [
            'pr' => round($committedPr + $convertedRemainder, 2),
            'po' => round($committedPo, 2),
            'total' => round($committedPr + $convertedRemainder + $committedPo, 2),
        ];
    }

    /**
     * @param  array{pr: float, po: float, total: float}  $breakdown
     * @return array{
     *   budget_total: float|null,
     *   committed: float,
     *   committed_pr: float,
     *   committed_po: float,
     *   available: float|null,
     *   utilization_percent: float|null,
     *   source: string
     * }
     */
    private function buildSnapshot(?float $budgetTotal, float $committed, array $breakdown, string $source): array
    {
        $available = $budgetTotal !== null ? max(0, round($budgetTotal - $committed, 2)) : null;
        $utilization = $budgetTotal !== null && $budgetTotal > 0
            ? round(($committed / $budgetTotal) * 100, 1)
            : null;

        return [
            'budget_total' => $budgetTotal,
            'committed' => $committed,
            'committed_pr' => $breakdown['pr'],
            'committed_po' => $breakdown['po'],
            'available' => $available,
            'utilization_percent' => $utilization,
            'source' => $source,
        ];
    }

    /**
     * @return array{
     *   budget_total: float|null,
     *   committed: float,
     *   committed_pr: float,
     *   committed_po: float,
     *   available: float|null,
     *   utilization_percent: float|null,
     *   source: string
     * }
     */
    private function emptySnapshot(): array
    {
        return [
            'budget_total' => null,
            'committed' => 0.0,
            'committed_pr' => 0.0,
            'committed_po' => 0.0,
            'available' => null,
            'utilization_percent' => null,
            'source' => 'none',
        ];
    }

    /**
     * @param  list<string>  $rolloutIds
     * @return array<string, float>
     */
    private function committedPoTotalsByRollout(array $rolloutIds): array
    {
        $openStatuses = [
            ProcurementPoStatus::APPROVED,
            ProcurementPoStatus::SENT,
            ProcurementPoStatus::PARTIALLY_RECEIVED,
        ];

        $rows = DB::connection('tenant')
            ->table('procurement_po_pr_links as link')
            ->join('procurement_prs as pr', 'pr.id', '=', 'link.pr_id')
            ->join('procurement_pos as po', 'po.id', '=', 'link.po_id')
            ->whereIn('pr.rollout_id', $rolloutIds)
            ->whereIn('po.status', $openStatuses)
            ->selectRaw('pr.rollout_id as rollout_id, po.id as po_id, po.grand_total as grand_total')
            ->distinct()
            ->get();

        $totals = [];
        foreach ($rolloutIds as $rolloutId) {
            $totals[$rolloutId] = 0.0;
        }

        foreach ($rows as $row) {
            $rolloutId = (string) $row->rollout_id;
            $totals[$rolloutId] = round($totals[$rolloutId] + (float) $row->grand_total, 2);
        }

        return $totals;
    }

    /**
     * @param  array<string, mixed>  $baseline
     */
    private function sumBaselineBuckets(array $baseline): float
    {
        $total = 0.0;
        foreach ($baseline as $value) {
            if (is_numeric($value)) {
                $total += (float) $value;
            }
        }

        return round($total, 2);
    }
}
