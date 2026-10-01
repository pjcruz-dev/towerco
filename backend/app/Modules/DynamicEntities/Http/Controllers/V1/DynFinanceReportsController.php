<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Http\Controllers\V1;

use App\Core\Http\Controllers\AbstractApiController;
use App\Modules\DynamicEntities\Services\AtcExtendedReportsService;
use App\Modules\DynamicEntities\Services\BankReconciliationReportService;
use App\Modules\DynamicEntities\Services\CapexOpexReportService;
use App\Modules\DynamicEntities\Services\CostVsBudgetReportService;
use App\Modules\DynamicEntities\Services\DailySalesReportService;
use App\Modules\DynamicEntities\Services\InvoiceAgingWorkbenchService;
use App\Modules\DynamicEntities\Services\MaterialsIssuedReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single dispatcher for GET dynamic-entities/reports/{report}.
 * Finance + extended keys must share one route — Laravel drops duplicate URI registrations.
 */
final class DynFinanceReportsController extends AbstractApiController
{
    /** @return list<string> */
    public static function reportKeys(): array
    {
        return [
            'capex-opex',
            'cost-vs-budget',
            'materials-issued',
            'daily-sales',
            'bank-reconciliation',
            'aging-invoice-adjustments',
            ...AtcExtendedReportsService::reportKeys(),
        ];
    }

    public function __invoke(
        Request $request,
        string $report,
        CapexOpexReportService $capexOpex,
        CostVsBudgetReportService $costVsBudget,
        MaterialsIssuedReportService $materials,
        DailySalesReportService $dailySales,
        BankReconciliationReportService $bankRec,
        InvoiceAgingWorkbenchService $invoiceAging,
        AtcExtendedReportsService $extended,
    ): JsonResponse {
        abort_unless($request->user()?->can('dynamic_entities:view'), 403);

        return match ($report) {
            'capex-opex' => $this->ok($capexOpex->build($request->validate([
                'region' => ['nullable', 'string', 'max:64'],
                'structure_type' => ['nullable', 'string', 'max:64'],
            ]))),
            'cost-vs-budget' => $this->ok($costVsBudget->build($request->validate([
                'region' => ['nullable', 'string', 'max:64'],
                'project_type' => ['nullable', 'string', 'max:64'],
            ]))),
            'materials-issued' => $this->ok($materials->build($request->validate([
                'date_from' => ['nullable', 'date'],
                'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
                'region' => ['nullable', 'string', 'max:64'],
            ]))),
            'daily-sales' => $this->ok($dailySales->build($request->validate([
                'date' => ['nullable', 'date'],
            ]))),
            'bank-reconciliation' => $this->ok($bankRec->build($request->validate([
                'bank_account_id' => ['nullable', 'string', 'max:64'],
                'period' => ['nullable', 'string', 'max:32'],
            ]))),
            'aging-invoice-adjustments' => $this->ok($invoiceAging->build($request->validate([
                'as_of' => ['nullable', 'date'],
                'customer' => ['nullable', 'string', 'max:255'],
                'bracket' => ['nullable', 'string', 'max:32'],
                'search' => ['nullable', 'string', 'max:255'],
            ]))),
            default => $this->ok($this->buildExtended($request, $report, $extended)),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExtended(Request $request, string $report, AtcExtendedReportsService $extended): array
    {
        abort_unless(in_array($report, AtcExtendedReportsService::reportKeys(), true), 404, 'Unknown report.');

        $filters = $request->validate([
            'as_of' => ['nullable', 'date'],
            'warehouse_id' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'period' => ['nullable', 'string', 'max:32'],
            'month' => ['nullable', 'string', 'max:7'],
        ]);

        return $extended->build($report, $filters);
    }
}
