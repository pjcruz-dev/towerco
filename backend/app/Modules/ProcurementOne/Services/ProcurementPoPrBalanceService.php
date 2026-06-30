<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPoPrLink;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;

final class ProcurementPoPrBalanceService
{
    public function openBalanceForPr(ProcurementPr $pr, ?string $excludePoId = null): float
    {
        $committed = $this->committedForPr($pr, $excludePoId);

        return max(0, round((float) $pr->estimated_total - $committed, 2));
    }

    public function committedForPr(ProcurementPr $pr, ?string $excludePoId = null): float
    {
        if ($excludePoId !== null) {
            $query = ProcurementPoPrLink::query()
                ->where('pr_id', $pr->id)
                ->whereHas('po', static function ($q) use ($excludePoId): void {
                    $q->whereNotIn('status', [
                        ProcurementPoStatus::CANCELLED,
                        ProcurementPoStatus::VOIDED,
                    ])->where('id', '<>', $excludePoId);
                });

            return (float) $query->sum('allocated_amount');
        }

        return $this->committedForPrIds([(string) $pr->id])[(string) $pr->id] ?? 0.0;
    }

    /**
     * @param  list<string>  $prIds
     * @return array<string, float>
     */
    public function committedForPrIds(array $prIds): array
    {
        $prIds = array_values(array_unique(array_filter($prIds, static fn (string $id): bool => $id !== '')));
        if ($prIds === []) {
            return [];
        }

        return ProcurementPoPrLink::query()
            ->whereIn('pr_id', $prIds)
            ->whereHas('po', static function ($q): void {
                $q->whereNotIn('status', [
                    ProcurementPoStatus::CANCELLED,
                    ProcurementPoStatus::VOIDED,
                ]);
            })
            ->groupBy('pr_id')
            ->selectRaw('pr_id, sum(allocated_amount) as aggregate')
            ->pluck('aggregate', 'pr_id')
            ->mapWithKeys(static fn ($committed, $prId): array => [(string) $prId => (float) $committed])
            ->all();
    }

    /**
     * @param  list<string>  $prIds
     * @return array<string, float>
     */
    public function openBalancesForPrIds(array $prIds): array
    {
        $prIds = array_values(array_unique(array_filter($prIds, static fn (string $id): bool => $id !== '')));
        if ($prIds === []) {
            return [];
        }

        $prs = ProcurementPr::query()
            ->whereIn('id', $prIds)
            ->get(['id', 'estimated_total']);

        $committedByPr = $this->committedForPrIds($prIds);
        $balances = [];

        foreach ($prs as $pr) {
            $committed = $committedByPr[(string) $pr->id] ?? 0.0;
            $balances[(string) $pr->id] = max(0, round((float) $pr->estimated_total - $committed, 2));
        }

        return $balances;
    }

    public function assertAllocationAllowed(ProcurementPr $pr, float $amount, ?string $excludePoId = null): void
    {
        abort_unless(
            in_array($pr->status, [ProcurementPrStatus::APPROVED, ProcurementPrStatus::CONVERTED], true),
            422,
            __('Purchase requisition :doc must be approved before creating a PO.', ['doc' => $pr->document_no ?? $pr->id]),
        );

        $open = $this->openBalanceForPr($pr, $excludePoId);
        if ($amount > $open + 0.0001) {
            abort(422, __('PO allocation :amount exceeds open PR balance :open for :doc.', [
                'amount' => number_format($amount, 2),
                'open' => number_format($open, 2),
                'doc' => $pr->document_no ?? $pr->title,
            ]));
        }
    }

    public function syncPrLink(ProcurementPo $po, ProcurementPr $pr, float $allocatedAmount): void
    {
        ProcurementPoPrLink::query()->updateOrCreate(
            ['po_id' => $po->id, 'pr_id' => $pr->id],
            ['allocated_amount' => round($allocatedAmount, 2)],
        );
    }

    public function refreshPurchaseRequisitionStatuses(ProcurementPo $po): void
    {
        $po->loadMissing('prLinks.purchaseRequisition');
        foreach ($po->prLinks as $link) {
            $pr = $link->purchaseRequisition;
            if (! $pr instanceof ProcurementPr) {
                continue;
            }

            $committed = $this->committedForPr($pr);

            $pr->committed_po_amount = round($committed, 2);
            $open = max(0, round((float) $pr->estimated_total - $committed, 2));

            if ($open <= 0.0001 && $committed > 0) {
                $pr->status = ProcurementPrStatus::CONVERTED;
            } elseif ($committed <= 0.0001 && $pr->status === ProcurementPrStatus::CONVERTED) {
                $pr->status = ProcurementPrStatus::APPROVED;
            } elseif ($committed > 0 && $open > 0.0001 && $pr->status === ProcurementPrStatus::CONVERTED) {
                $pr->status = ProcurementPrStatus::APPROVED;
            }

            $pr->save();
        }
    }

    /**
     * @param  list<string>  $prIds
     * @return list<ProcurementPr>
     */
    public function resolveApprovedPurchaseRequisitions(array $prIds): array
    {
        $prs = ProcurementPr::query()->whereIn('id', $prIds)->get();
        abort_if($prs->count() !== count(array_unique($prIds)), 422, __('One or more purchase requisitions were not found.'));

        return $prs->all();
    }
}
