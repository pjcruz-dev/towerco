<?php

declare(strict_types=1);

namespace App\Modules\ProcurementOne\Services;

use App\Modules\EApproval\Services\EApprovalFinanceProcurementKpiService;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\ProcurementOne\Models\ProcurementApInvoice;
use App\Modules\ProcurementOne\Models\ProcurementContract;
use App\Modules\ProcurementOne\Models\ProcurementGrn;
use App\Modules\ProcurementOne\Models\ProcurementPaymentRequest;
use App\Modules\ProcurementOne\Models\ProcurementPo;
use App\Modules\ProcurementOne\Models\ProcurementPr;
use App\Modules\ProcurementOne\Support\ProcurementApInvoiceStatus;
use App\Modules\ProcurementOne\Support\ProcurementContractStatus;
use App\Modules\ProcurementOne\Support\ProcurementGrnStatus;
use App\Modules\ProcurementOne\Support\ProcurementPaymentRequestStatus;
use App\Modules\ProcurementOne\Support\ProcurementPoStatus;
use App\Modules\ProcurementOne\Support\ProcurementPrStatus;
use Illuminate\Support\Facades\Cache;

final class ProcurementOneDashboardService
{
    public function __construct(
        private readonly ProcurementBudgetUtilizationService $budgetUtilization,
        private readonly ProcurementApInvoiceAgingService $apAging,
        private readonly ProcurementOnePlanFeaturesService $planFeatures,
        private readonly ProcurementContractExpiringService $contractExpiring,
        private readonly ProcurementP2pDashboardService $p2pDashboard,
        private readonly ProcurementVendorSpendDashboardService $vendorSpend,
        private readonly ProcurementExportDateRangeService $exportDateRange,
        private readonly ProcurementDocumentScopeService $documentScope,
    ) {}

    /**
     * @return array{
     *   kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   budget_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   ap_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   ap_aging: array<string, mixed>,
     *   payment_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   contract_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   p2p: array<string, mixed>,
     *   vendor_spend: array<string, mixed>,
     *   finance_kpis: list<array{key: string, label: string, value: string, change?: string, tone?: string}>,
     *   message: string
     * }
     */
    public function build(TenantUser $user): array
    {
        $tenantId = (string) (tenant('id') ?? 'unknown');
        $planTier = $this->planFeatures->snapshot()['plan_tier'] ?? 'starter';

        return Cache::remember(
            "procurement-one:dashboard:{$tenantId}:{$user->id}:{$planTier}",
            30,
            fn (): array => $this->buildUncached($user),
        );
    }

    /**
     * @return array{
     *   kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   budget_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   ap_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   ap_aging: array<string, mixed>,
     *   payment_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   contract_kpis: list<array{key: string, label: string, value: int|string, tone?: string}>,
     *   p2p: array<string, mixed>,
     *   vendor_spend: array<string, mixed>,
     *   finance_kpis: list<array{key: string, label: string, value: string, change?: string, tone?: string}>,
     *   message: string
     * }
     */
    private function buildUncached(TenantUser $user): array
    {
        $settings = app(ProcurementOneSettingsService::class);
        $message = trim((string) $settings->getString(ProcurementOneSettingsService::MODULE_MESSAGE, ''));
        if ($message === '') {
            $message = 'Purchase requisitions, purchase orders, and goods receipts — lifecycle documents with E-Approval integration.';
        }

        $budgetSummary = $this->aggregateBudgetSummary();
        $aging = $this->apAging->snapshot();
        $financeCounts = app(EApprovalFinanceProcurementKpiService::class)->counts();
        $financeKpis = app(EApprovalFinanceProcurementKpiService::class)->kpiCards($financeCounts);
        $p2p = $this->planFeatures->reportingExportsEnabled() ? $this->p2pDashboard->snapshot() : [];
        $vendorSpend = $this->planFeatures->reportingExportsEnabled()
            ? $this->vendorSpend->snapshot(['period' => 'current_month'], $this->exportDateRange)
            : [];
        $planFeatures = $this->planFeatures->snapshot();

        $prQuery = $this->documentScope->applyRequestorScope(ProcurementPr::query(), $user);
        $poQuery = $this->documentScope->applyRequestorScope(ProcurementPo::query(), $user);
        $grnQuery = ProcurementGrn::query();
        if (! $this->documentScope->canManageDocuments($user)) {
            $grnQuery->whereHas('purchaseOrder', static fn ($query) => $query->where('requestor_id', (string) $user->id));
        }
        $apQuery = $this->documentScope->applyRequestorScope(ProcurementApInvoice::query(), $user);
        $paymentQuery = $this->documentScope->applyRequestorScope(ProcurementPaymentRequest::query(), $user);

        return [
            'kpis' => [
                ['key' => 'draft_pr', 'label' => 'Draft PRs', 'value' => (clone $prQuery)->where('status', ProcurementPrStatus::DRAFT)->count(), 'tone' => 'neutral'],
                ['key' => 'pending_approval', 'label' => 'Pending approval', 'value' => (clone $prQuery)->where('status', ProcurementPrStatus::PENDING_APPROVAL)->count(), 'tone' => 'warning'],
                ['key' => 'approved_pr', 'label' => 'Approved PRs', 'value' => (clone $prQuery)->whereIn('status', [ProcurementPrStatus::APPROVED, ProcurementPrStatus::CONVERTED])->count(), 'tone' => 'neutral'],
                ['key' => 'open_po', 'label' => 'Open POs', 'value' => (clone $poQuery)->whereIn('status', [
                    ProcurementPoStatus::APPROVED,
                    ProcurementPoStatus::SENT,
                    ProcurementPoStatus::PARTIALLY_RECEIVED,
                ])->count(), 'tone' => 'neutral'],
                ['key' => 'draft_grn', 'label' => 'Draft GRNs', 'value' => (clone $grnQuery)->where('status', ProcurementGrnStatus::DRAFT)->count(), 'tone' => 'neutral'],
            ],
            'budget_kpis' => [
                ['key' => 'budget_total', 'label' => 'Budget (tracked rollouts)', 'value' => $budgetSummary['budget_total'] ?? '—', 'tone' => 'neutral'],
                ['key' => 'committed', 'label' => 'Committed (PR + PO)', 'value' => $budgetSummary['committed'] ?? '—', 'tone' => 'warning'],
                ['key' => 'available', 'label' => 'Available', 'value' => $budgetSummary['available'] ?? '—', 'tone' => 'success'],
                ['key' => 'utilization_percent', 'label' => 'Utilization %', 'value' => $budgetSummary['utilization_percent'] !== null ? $budgetSummary['utilization_percent'].'%' : '—', 'tone' => 'neutral'],
            ],
            'ap_kpis' => [
                ['key' => 'pending_ap', 'label' => 'Pending AP invoices', 'value' => (clone $apQuery)->where('status', ProcurementApInvoiceStatus::PENDING_APPROVAL)->count(), 'tone' => 'warning'],
                ['key' => 'open_ap', 'label' => 'Open AP balance', 'value' => number_format($aging['total_open'], 2, '.', ''), 'tone' => 'neutral'],
                ['key' => 'open_ap_count', 'label' => 'Open invoices', 'value' => $aging['total_count'], 'tone' => 'neutral'],
            ],
            'ap_aging' => $aging,
            'payment_kpis' => $this->planFeatures->paymentTrackingEnabled() ? [
                ['key' => 'pending_payment_approval', 'label' => 'Pending payment approval', 'value' => (clone $paymentQuery)->where('status', ProcurementPaymentRequestStatus::PENDING_APPROVAL)->count(), 'tone' => 'warning'],
                ['key' => 'scheduled_payments', 'label' => 'Scheduled payments', 'value' => (clone $paymentQuery)->where('status', ProcurementPaymentRequestStatus::SCHEDULED)->count(), 'tone' => 'neutral'],
                ['key' => 'paid_unreconciled', 'label' => 'Paid (unreconciled)', 'value' => (clone $paymentQuery)->where('status', ProcurementPaymentRequestStatus::PAID)->count(), 'tone' => 'neutral'],
            ] : [],
            'contract_kpis' => $this->planFeatures->vendorContractsEnabled() ? $this->contractKpis() : [],
            'p2p' => $p2p,
            'vendor_spend' => $vendorSpend,
            'finance_kpis' => $financeKpis,
            'plan_features' => $planFeatures,
            'message' => $message,
        ];
    }

    /**
     * @return list<array{key: string, label: string, value: int|string, tone?: string}>
     */
    private function contractKpis(): array
    {
        $expiring = $this->contractExpiring->summaryCounts();

        return [
            ['key' => 'active_contracts', 'label' => 'Active contracts', 'value' => ProcurementContract::query()->where('status', ProcurementContractStatus::ACTIVE)->count(), 'tone' => 'neutral'],
            ['key' => 'expiring_30', 'label' => 'Expiring in 30 days', 'value' => $expiring['within_30'], 'tone' => 'warning'],
            ['key' => 'expiring_90', 'label' => 'Expiring in 90 days', 'value' => $expiring['within_90'], 'tone' => 'neutral'],
        ];
    }

    /**
     * @return array{budget_total: float|null, committed: float, available: float|null, utilization_percent: float|null}
     */
    private function aggregateBudgetSummary(): array
    {
        return $this->budgetUtilization->aggregateSummaryForTrackedRollouts();
    }
}
