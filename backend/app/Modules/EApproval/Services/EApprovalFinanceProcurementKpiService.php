<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Services;

final class EApprovalFinanceProcurementKpiService
{
    public function __construct(
        private readonly EApprovalCashAdvanceService $cashAdvances,
        private readonly EApprovalPurchaseRequisitionService $purchaseRequisitions,
    ) {}

    /**
     * @return array{open_cash_advances: int, unliquidated_cash_advances: int, prs_without_po: int}
     */
    public function counts(): array
    {
        return [
            'open_cash_advances' => $this->cashAdvances->countOpenTenantWide(),
            'unliquidated_cash_advances' => $this->cashAdvances->countUnliquidatedTenantWide(),
            'prs_without_po' => $this->purchaseRequisitions->countWithoutPoTenantWide(),
        ];
    }

    /**
     * @param  array{open_cash_advances: int, unliquidated_cash_advances: int, prs_without_po: int}  $counts
     * @return list<array{key: string, label: string, value: string, change: string, tone: string}>
     */
    public function kpiCards(array $counts): array
    {
        $openCashAdvances = (int) ($counts['open_cash_advances'] ?? 0);
        $unliquidated = (int) ($counts['unliquidated_cash_advances'] ?? 0);
        $prsWithoutPo = (int) ($counts['prs_without_po'] ?? 0);

        return [
            [
                'key' => 'open_cash_advances',
                'label' => 'Open cash advances',
                'value' => (string) $openCashAdvances,
                'change' => 'Approved with remaining balance',
                'tone' => $openCashAdvances > 0 ? 'warning' : 'success',
            ],
            [
                'key' => 'unliquidated_cash_advances',
                'label' => 'Unliquidated CAs',
                'value' => (string) $unliquidated,
                'change' => 'No liquidation filed yet',
                'tone' => $unliquidated > 0 ? 'danger' : 'success',
            ],
            [
                'key' => 'prs_without_po',
                'label' => 'PRs without PO',
                'value' => (string) $prsWithoutPo,
                'change' => 'Approved requisitions not yet ordered',
                'tone' => $prsWithoutPo > 0 ? 'warning' : 'success',
            ],
        ];
    }

    /**
     * @param  array{open_cash_advances: int, unliquidated_cash_advances: int, prs_without_po: int}  $counts
     * @return list<array{id: string, label: string, count: int, href: string, priority: string}>
     */
    public function actions(array $counts): array
    {
        $actions = [];

        $openCashAdvances = (int) ($counts['open_cash_advances'] ?? 0);
        if ($openCashAdvances > 0) {
            $actions[] = [
                'id' => 'fp-open-cash-advances',
                'label' => 'Review open cash advances',
                'count' => $openCashAdvances,
                'href' => '/e-approval/submissions',
                'priority' => 'normal',
            ];
        }

        $unliquidated = (int) ($counts['unliquidated_cash_advances'] ?? 0);
        if ($unliquidated > 0) {
            $actions[] = [
                'id' => 'fp-unliquidated-cash-advances',
                'label' => 'Unliquidated cash advances',
                'count' => $unliquidated,
                'href' => '/e-approval/submissions',
                'priority' => 'high',
            ];
        }

        $prsWithoutPo = (int) ($counts['prs_without_po'] ?? 0);
        if ($prsWithoutPo > 0) {
            $actions[] = [
                'id' => 'fp-prs-without-po',
                'label' => 'PRs awaiting purchase order',
                'count' => $prsWithoutPo,
                'href' => '/e-approval/submissions',
                'priority' => 'normal',
            ];
        }

        return $actions;
    }
}
