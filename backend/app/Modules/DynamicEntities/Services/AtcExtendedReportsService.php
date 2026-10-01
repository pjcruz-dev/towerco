<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;
use Carbon\Carbon;

/**
 * Metacoresoft-parity dashboards and operational / finance reports over dyn_records.
 */
final class AtcExtendedReportsService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function build(string $report, array $filters = []): array
    {
        return match ($report) {
            'cas-executive-dashboard' => $this->casExecutiveDashboard(),
            'ar-ap-aging' => $this->arApAging($filters),
            'stock-on-hand' => $this->stockOnHand($filters),
            'petty-cash' => $this->pettyCash($filters),
            'live-tower-build-forecasts' => $this->liveTowerBuildForecasts(),
            'site-portfolio-status' => $this->sitePortfolioStatus(),
            'rfti-pipeline' => $this->rftiPipeline(),
            'saq-milestone-aging' => $this->saqMilestoneAging(),
            'permit-status-compliance' => $this->permitStatusCompliance(),
            'energization-power-status' => $this->energizationPowerStatus(),
            'colocation-tenancy' => $this->colocationTenancy(),
            'trial-balance' => $this->trialBalance($filters),
            'income-statement' => $this->incomeStatement($filters),
            'balance-sheet' => $this->balanceSheet($filters),
            'cash-flow' => $this->cashFlow($filters),
            'monthly-management-accounts' => $this->monthlyManagementAccounts($filters),
            'bir-compliance' => $this->birCompliance(),
            default => abort(404, 'Unknown extended report.'),
        };
    }

    /**
     * @return list<string>
     */
    public static function reportKeys(): array
    {
        return [
            'cas-executive-dashboard',
            'ar-ap-aging',
            'stock-on-hand',
            'petty-cash',
            'live-tower-build-forecasts',
            'site-portfolio-status',
            'rfti-pipeline',
            'saq-milestone-aging',
            'permit-status-compliance',
            'energization-power-status',
            'colocation-tenancy',
            'trial-balance',
            'income-statement',
            'balance-sheet',
            'cash-flow',
            'monthly-management-accounts',
            'bir-compliance',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function casExecutiveDashboard(): array
    {
        $salesMtd = 0.0;
        $purchasesMtd = 0.0;
        $cashIn = 0.0;
        $cashOut = 0.0;
        $bankBalance = 0.0;
        $openAr = 0.0;
        $openAp = 0.0;
        $start = now()->startOfMonth();

        foreach (DynReportSupport::recordsForSlug('sales_transactions') as $row) {
            $values = $row->values_json ?? [];
            $date = DynReportSupport::parseDate($values['transaction_date'] ?? null);
            $amount = DynReportSupport::money($values['total_amount'] ?? $values['balance_due'] ?? 0);
            $status = strtolower((string) ($row->status ?? $values['status'] ?? ''));
            if ($date !== null && ! $date->lt($start)) {
                $salesMtd += $amount;
            }
            if (! str_contains($status, 'paid') && ! str_contains($status, 'void') && ! str_contains($status, 'cancel')) {
                $openAr += DynReportSupport::money($values['balance_due'] ?? $values['total_amount'] ?? 0);
            }
        }

        foreach (DynReportSupport::recordsForSlug('purchase_transactions') as $row) {
            $values = $row->values_json ?? [];
            $date = DynReportSupport::parseDate($values['purchase_date'] ?? $values['transaction_date'] ?? null);
            $amount = DynReportSupport::money($values['total_amount'] ?? $values['balance_due'] ?? 0);
            $status = strtolower((string) ($row->status ?? $values['status'] ?? ''));
            if ($date !== null && ! $date->lt($start)) {
                $purchasesMtd += $amount;
            }
            if (! str_contains($status, 'paid') && ! str_contains($status, 'void') && ! str_contains($status, 'cancel')) {
                $openAp += DynReportSupport::money($values['balance_due'] ?? $values['total_amount'] ?? 0);
            }
        }

        foreach (DynReportSupport::recordsForSlug('payments_and_receipts') as $row) {
            $values = $row->values_json ?? [];
            $date = DynReportSupport::parseDate($values['payment_date'] ?? null);
            if ($date === null || $date->lt($start)) {
                continue;
            }
            $amount = DynReportSupport::money($values['amount'] ?? 0);
            $type = strtolower((string) ($values['type'] ?? ''));
            if (str_contains($type, 'receipt') || str_contains($type, 'collection')) {
                $cashIn += $amount;
            } else {
                $cashOut += $amount;
            }
        }

        foreach (DynReportSupport::recordsForSlug('bank_accounts') as $row) {
            $bankBalance += DynReportSupport::money(($row->values_json ?? [])['current_balance'] ?? 0);
        }

        return $this->pack(
            kpis: [
                $this->kpi('sales_mtd', 'Sales MTD', $salesMtd, 'money'),
                $this->kpi('purchases_mtd', 'Purchases MTD', $purchasesMtd, 'money'),
                $this->kpi('open_ar', 'Open AR', $openAr, 'money'),
                $this->kpi('open_ap', 'Open AP', $openAp, 'money'),
                $this->kpi('cash_in', 'Cash in (MTD)', $cashIn, 'money'),
                $this->kpi('cash_out', 'Cash out (MTD)', $cashOut, 'money'),
                $this->kpi('bank_balance', 'Bank balances', $bankBalance, 'money'),
                $this->kpi('net_cash_mtd', 'Net cash MTD', $cashIn - $cashOut, 'money'),
            ],
            charts: [
                $this->chart('cash', 'Cash movement MTD', 'donut', [
                    ['key' => 'in', 'label' => 'Receipts', 'value' => round($cashIn, 2)],
                    ['key' => 'out', 'label' => 'Disbursements', 'value' => round($cashOut, 2)],
                ], 'Amount'),
                $this->chart('arap', 'Open AR vs AP', 'bar', [
                    ['key' => 'ar', 'label' => 'Accounts receivable', 'value' => round($openAr, 2)],
                    ['key' => 'ap', 'label' => 'Accounts payable', 'value' => round($openAp, 2)],
                ], 'Amount'),
            ],
            tables: [],
            links: [
                ['label' => 'AR & AP Aging', 'href' => '/dynamic-entities/reports/ar-ap-aging'],
                ['label' => 'Daily Sales', 'href' => '/dynamic-entities/reports/daily-sales'],
                ['label' => 'Bank Reconciliation', 'href' => '/dynamic-entities/reports/bank-reconciliation'],
                ['label' => 'Trial Balance', 'href' => '/dynamic-entities/reports/trial-balance'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function arApAging(array $filters): array
    {
        $asOf = DynReportSupport::parseDate($filters['as_of'] ?? null) ?? now()->startOfDay();
        $buckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $arRows = [];
        $apRows = [];

        foreach (DynReportSupport::recordsForSlug('sales_transactions') as $row) {
            $values = $row->values_json ?? [];
            $status = strtolower((string) ($row->status ?? $values['status'] ?? ''));
            if (str_contains($status, 'paid') || str_contains($status, 'void') || str_contains($status, 'cancel')) {
                continue;
            }
            $amount = DynReportSupport::money($values['balance_due'] ?? $values['total_amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $due = DynReportSupport::parseDate($values['due_date'] ?? $values['transaction_date'] ?? null) ?? $asOf;
            $bucket = $this->agingBucket($due, $asOf);
            $buckets[$bucket] += $amount;
            $arRows[] = [
                'ref' => (string) ($values['transaction_number'] ?? $row->title ?? ''),
                'party' => (string) ($values['customer_name'] ?? $values['customer_id'] ?? '—'),
                'due_date' => $due->toDateString(),
                'bucket' => $bucket,
                'amount' => round($amount, 2),
                'side' => 'AR',
            ];
        }

        $apBuckets = ['current' => 0.0, '1_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        foreach (DynReportSupport::recordsForSlug('purchase_transactions') as $row) {
            $values = $row->values_json ?? [];
            $status = strtolower((string) ($row->status ?? $values['status'] ?? ''));
            if (str_contains($status, 'paid') || str_contains($status, 'void') || str_contains($status, 'cancel')) {
                continue;
            }
            $amount = DynReportSupport::money($values['balance_due'] ?? $values['total_amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            $due = DynReportSupport::parseDate($values['due_date'] ?? $values['purchase_date'] ?? $values['transaction_date'] ?? null) ?? $asOf;
            $bucket = $this->agingBucket($due, $asOf);
            $apBuckets[$bucket] += $amount;
            $apRows[] = [
                'ref' => (string) ($values['purchase_number'] ?? $values['transaction_number'] ?? $row->title ?? ''),
                'party' => (string) ($values['supplier_name'] ?? $values['supplier_id'] ?? '—'),
                'due_date' => $due->toDateString(),
                'bucket' => $bucket,
                'amount' => round($amount, 2),
                'side' => 'AP',
            ];
        }

        $arTotal = array_sum($buckets);
        $apTotal = array_sum($apBuckets);

        return $this->pack(
            kpis: [
                $this->kpi('ar_total', 'Open AR', $arTotal, 'money'),
                $this->kpi('ap_total', 'Open AP', $apTotal, 'money'),
                $this->kpi('net', 'Net (AR − AP)', $arTotal - $apTotal, 'money'),
                $this->kpi('ar_overdue', 'AR 90+ days', $buckets['90_plus'], 'money'),
                $this->kpi('ap_overdue', 'AP 90+ days', $apBuckets['90_plus'], 'money'),
            ],
            charts: [
                $this->chart('ar', 'AR aging', 'bar', $this->agingChart($buckets), 'Amount'),
                $this->chart('ap', 'AP aging', 'bar', $this->agingChart($apBuckets), 'Amount'),
            ],
            tables: [
                $this->table('ar', 'Accounts receivable', [
                    ['key' => 'ref', 'label' => 'Reference'],
                    ['key' => 'party', 'label' => 'Customer'],
                    ['key' => 'due_date', 'label' => 'Due'],
                    ['key' => 'bucket', 'label' => 'Bucket'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
                ], array_slice($arRows, 0, 50)),
                $this->table('ap', 'Accounts payable', [
                    ['key' => 'ref', 'label' => 'Reference'],
                    ['key' => 'party', 'label' => 'Supplier'],
                    ['key' => 'due_date', 'label' => 'Due'],
                    ['key' => 'bucket', 'label' => 'Bucket'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
                ], array_slice($apRows, 0, 50)),
            ],
            links: [
                ['label' => 'Sales Transactions', 'href' => '/dynamic-entities/sales_transactions'],
                ['label' => 'Purchase Transactions', 'href' => '/dynamic-entities/purchase_transactions'],
            ],
            filterOptions: ['as_of' => $asOf->toDateString()],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function stockOnHand(array $filters): array
    {
        $warehouseFilter = $this->nullableString($filters['warehouse_id'] ?? null);
        $products = DynReportSupport::titleMap('products', ['product_name', 'name', 'description']);
        $warehouses = DynReportSupport::titleMap('warehouses', ['warehouse_name', 'name']);
        $balances = [];

        foreach (DynReportSupport::recordsForSlug('stock_movements') as $movement) {
            $values = $movement->values_json ?? [];
            $warehouseId = (string) ($values['warehouse_id'] ?? '');
            if ($warehouseFilter !== null && $warehouseId !== $warehouseFilter) {
                continue;
            }
            $productId = (string) ($values['product_id'] ?? '');
            $qty = DynReportSupport::money($values['quantity'] ?? 0);
            $type = strtolower((string) ($values['movement_type'] ?? $values['type'] ?? ''));
            $sign = 1.0;
            if (str_contains($type, 'issue') || str_contains($type, 'out') || str_contains($type, 'transfer_out')) {
                $sign = -1.0;
            } elseif (str_contains($type, 'return') || str_contains($type, 'receipt') || str_contains($type, 'in') || str_contains($type, 'adjust')) {
                $sign = 1.0;
            }
            $key = ($warehouseId !== '' ? $warehouseId : '_').'|'.($productId !== '' ? $productId : '_');
            if (! isset($balances[$key])) {
                $balances[$key] = [
                    'warehouse_id' => $warehouseId,
                    'product_id' => $productId,
                    'qty' => 0.0,
                    'value' => 0.0,
                ];
            }
            $balances[$key]['qty'] += $sign * $qty;
            $balances[$key]['value'] += $sign * DynReportSupport::money($values['total_cost'] ?? 0);
        }

        $rows = [];
        $totalQty = 0.0;
        $totalValue = 0.0;
        $byWarehouse = [];
        foreach ($balances as $row) {
            if (abs($row['qty']) < 0.0001 && abs($row['value']) < 0.01) {
                continue;
            }
            $wh = $warehouses[$row['warehouse_id']] ?? ($row['warehouse_id'] !== '' ? DynReportSupport::shortId($row['warehouse_id']) : '—');
            $product = $products[$row['product_id']] ?? ($row['product_id'] !== '' ? DynReportSupport::shortId($row['product_id']) : '(unspecified)');
            $rows[] = [
                'warehouse' => $wh,
                'product' => $product,
                'qty' => round($row['qty'], 2),
                'value' => round($row['value'], 2),
            ];
            $totalQty += $row['qty'];
            $totalValue += $row['value'];
            $byWarehouse[$wh] = ($byWarehouse[$wh] ?? 0) + $row['value'];
        }

        usort($rows, fn (array $a, array $b): int => $b['value'] <=> $a['value']);

        return $this->pack(
            kpis: [
                $this->kpi('skus', 'SKU lines', count($rows), 'number'),
                $this->kpi('qty', 'Net quantity', $totalQty, 'number'),
                $this->kpi('value', 'Inventory value', $totalValue, 'money'),
            ],
            charts: [
                $this->chart('wh', 'Value by warehouse', 'donut', collect($byWarehouse)
                    ->map(fn (float $v, string $k): array => ['key' => $k, 'label' => $k, 'value' => round($v, 2)])
                    ->values()
                    ->all(), 'Value'),
            ],
            tables: [
                $this->table('soh', 'Stock on hand', [
                    ['key' => 'warehouse', 'label' => 'Warehouse'],
                    ['key' => 'product', 'label' => 'Product'],
                    ['key' => 'qty', 'label' => 'Qty', 'format' => 'number'],
                    ['key' => 'value', 'label' => 'Value', 'format' => 'money'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [
                ['label' => 'Stock Movements', 'href' => '/dynamic-entities/stock_movements'],
                ['label' => 'Materials & Equipment', 'href' => '/dynamic-entities/products'],
                ['label' => 'Warehouses', 'href' => '/dynamic-entities/warehouses'],
            ],
            filterOptions: [
                'warehouses' => collect($warehouses)->map(fn (string $name, string $id): array => [
                    'id' => $id,
                    'name' => $name,
                ])->values()->all(),
            ],
            message: count($rows) === 0 ? 'No stock movements found. Import stock movements or seed demo data.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function pettyCash(array $filters): array
    {
        $dateFrom = DynReportSupport::parseDate($filters['date_from'] ?? null);
        $dateTo = DynReportSupport::parseDate($filters['date_to'] ?? null);
        $rows = [];
        $total = 0.0;
        $byMethod = [];

        foreach (DynReportSupport::recordsForSlug('payments_and_receipts') as $row) {
            $values = $row->values_json ?? [];
            $type = strtolower((string) ($values['type'] ?? ''));
            $method = strtolower((string) ($values['payment_method'] ?? ''));
            $isPetty = str_contains($type, 'petty')
                || str_contains($method, 'petty')
                || (str_contains($method, 'cash') && str_contains($type, 'disbursement'));
            if (! $isPetty) {
                continue;
            }
            $date = DynReportSupport::parseDate($values['payment_date'] ?? null);
            if ($dateFrom !== null && ($date === null || $date->lt($dateFrom))) {
                continue;
            }
            if ($dateTo !== null && ($date === null || $date->gt($dateTo))) {
                continue;
            }
            $amount = DynReportSupport::money($values['amount'] ?? 0);
            $total += $amount;
            $methodLabel = (string) ($values['payment_method'] ?? 'Cash');
            $byMethod[$methodLabel] = ($byMethod[$methodLabel] ?? 0) + $amount;
            $rows[] = [
                'payment_number' => (string) ($values['payment_number'] ?? $row->title ?? ''),
                'date' => (string) ($values['payment_date'] ?? ''),
                'type' => (string) ($values['type'] ?? ''),
                'method' => $methodLabel,
                'description' => (string) ($values['description'] ?? ''),
                'status' => (string) ($row->status ?? $values['status'] ?? ''),
                'amount' => round($amount, 2),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $b['date'], (string) $a['date']));

        return $this->pack(
            kpis: [
                $this->kpi('vouchers', 'Vouchers', count($rows), 'number'),
                $this->kpi('total', 'Total disbursed', $total, 'money'),
            ],
            charts: [
                $this->chart('method', 'By payment method', 'donut', collect($byMethod)
                    ->map(fn (float $v, string $k): array => ['key' => $k, 'label' => $k, 'value' => round($v, 2)])
                    ->values()
                    ->all(), 'Amount'),
            ],
            tables: [
                $this->table('pc', 'Petty cash / cash disbursements', [
                    ['key' => 'payment_number', 'label' => 'Number'],
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'method', 'label' => 'Method'],
                    ['key' => 'description', 'label' => 'Description'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [
                ['label' => 'Payments & Receipts', 'href' => '/dynamic-entities/payments_and_receipts'],
                ['label' => 'Bank Transactions', 'href' => '/dynamic-entities/bank_transactions'],
            ],
            message: count($rows) === 0
                ? 'No petty cash or cash disbursement rows yet. Use Payments & Receipts with method Cash.'
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function liveTowerBuildForecasts(): array
    {
        $sites = DynReportSupport::siteIndex();
        $byMonth = [];
        $rows = [];
        foreach (DynReportSupport::recordsForSlug('construction_projects') as $project) {
            $values = $project->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $site = $sites[$siteId]['values'] ?? [];
            $finish = DynReportSupport::parseDate($values['finish_plan'] ?? $values['planned_finish'] ?? $values['rfti_target'] ?? null);
            $monthKey = $finish?->format('Y-m') ?? 'Unscheduled';
            $byMonth[$monthKey] = ($byMonth[$monthKey] ?? 0) + 1;
            $pct = DynReportSupport::money($values['percentage_in_progress'] ?? $values['progress'] ?? 0);
            $rows[] = [
                'project' => (string) ($project->title ?? $values['project_name'] ?? ''),
                'site' => (string) ($site['site_name'] ?? $site['site_code'] ?? DynReportSupport::shortId($siteId)),
                'region' => (string) ($site['region'] ?? '—'),
                'status' => (string) ($project->status ?? $values['status'] ?? ''),
                'progress' => round($pct, 1),
                'finish_plan' => $finish?->toDateString() ?? '—',
                'budget' => round(DynReportSupport::money($values['budget'] ?? 0), 2),
                'actual' => round(DynReportSupport::money($values['total_cost'] ?? 0), 2),
            ];
        }
        ksort($byMonth);

        return $this->pack(
            kpis: [
                $this->kpi('projects', 'Projects', count($rows), 'number'),
                $this->kpi('scheduled', 'With finish plan', count(array_filter($rows, fn ($r) => $r['finish_plan'] !== '—')), 'number'),
            ],
            charts: [
                $this->chart('forecast', 'Planned finishes by month', 'bar', collect($byMonth)
                    ->map(fn (int $v, string $k): array => ['key' => $k, 'label' => $k, 'value' => $v])
                    ->values()
                    ->all(), 'Projects'),
            ],
            tables: [
                $this->table('forecast', 'LIVE build & forecasts', [
                    ['key' => 'project', 'label' => 'Project'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'progress', 'label' => 'Progress %', 'format' => 'number'],
                    ['key' => 'finish_plan', 'label' => 'Finish plan'],
                    ['key' => 'budget', 'label' => 'Budget', 'format' => 'money'],
                    ['key' => 'actual', 'label' => 'Actual', 'format' => 'money'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [
                ['label' => 'Construction Projects', 'href' => '/dynamic-entities/construction_projects'],
                ['label' => 'Executive Dashboard', 'href' => '/dynamic-entities/executive-dashboard'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sitePortfolioStatus(): array
    {
        $sites = DynReportSupport::siteIndex();
        $byRegion = [];
        $byStatus = [];
        $byType = [];
        $rows = [];
        foreach ($sites as $site) {
            $values = $site['values'];
            $region = (string) ($values['region'] ?? '—');
            $status = (string) ($values['site_status'] ?? $site['status'] ?? 'Unknown');
            $type = (string) ($values['project_type'] ?? '—');
            $byRegion[$region] = ($byRegion[$region] ?? 0) + 1;
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $byType[$type] = ($byType[$type] ?? 0) + 1;
            $rows[] = [
                'site_code' => (string) ($values['site_code'] ?? $site['source_external_id'] ?? ''),
                'site_name' => (string) ($values['site_name'] ?? $site['title'] ?? ''),
                'region' => $region,
                'project_type' => $type,
                'status' => $status,
                'milestone' => (string) ($values['milestone'] ?? $values['project_milestone'] ?? '—'),
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('sites', 'Sites', count($sites), 'number'),
                $this->kpi('regions', 'Regions', count($byRegion), 'number'),
                $this->kpi('statuses', 'Status buckets', count($byStatus), 'number'),
            ],
            charts: [
                $this->chart('region', 'By region', 'bar', $this->toChart($byRegion), 'Sites'),
                $this->chart('status', 'By status', 'donut', $this->toChart($byStatus, 12), 'Sites'),
                $this->chart('type', 'By project type', 'donut', $this->toChart($byType), 'Sites'),
            ],
            tables: [
                $this->table('portfolio', 'Site portfolio', [
                    ['key' => 'site_code', 'label' => 'Code'],
                    ['key' => 'site_name', 'label' => 'Name'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'project_type', 'label' => 'Type'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'milestone', 'label' => 'Milestone'],
                ], array_slice($rows, 0, 150)),
            ],
            links: [['label' => 'Tower Sites', 'href' => '/dynamic-entities/tower_sites']],
            message: count($sites) === 0 ? 'Import tower sites to populate portfolio status.' : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rftiPipeline(): array
    {
        $aging = ['on_track' => 0, 'due_30' => 0, 'overdue' => 0, 'unknown' => 0];
        $byMilestone = [];
        $rows = [];
        foreach (DynReportSupport::siteIndex() as $site) {
            $values = $site['values'];
            $milestone = (string) ($values['milestone'] ?? $values['project_milestone'] ?? 'Unassigned');
            $byMilestone[$milestone] = ($byMilestone[$milestone] ?? 0) + 1;
            $days = $values['days_to_rfti'] ?? null;
            $bucket = 'unknown';
            if ($days !== null && $days !== '' && is_numeric($days)) {
                $d = (float) $days;
                if ($d < 0) {
                    $bucket = 'overdue';
                } elseif ($d <= 30) {
                    $bucket = 'due_30';
                } else {
                    $bucket = 'on_track';
                }
            }
            $aging[$bucket]++;
            $rows[] = [
                'site' => (string) ($values['site_name'] ?? $site['title'] ?? ''),
                'region' => (string) ($values['region'] ?? '—'),
                'milestone' => $milestone,
                'days_to_rfti' => is_numeric($days) ? (float) $days : null,
                'bucket' => $bucket,
                'status' => (string) ($values['site_status'] ?? $site['status'] ?? ''),
            ];
        }
        usort($rows, fn (array $a, array $b): int => ($a['days_to_rfti'] ?? 9999) <=> ($b['days_to_rfti'] ?? 9999));

        return $this->pack(
            kpis: [
                $this->kpi('overdue', 'Overdue', $aging['overdue'], 'number'),
                $this->kpi('due_30', 'Due ≤30d', $aging['due_30'], 'number'),
                $this->kpi('on_track', 'On track', $aging['on_track'], 'number'),
                $this->kpi('unknown', 'No SLA date', $aging['unknown'], 'number'),
            ],
            charts: [
                $this->chart('aging', 'RFTI SLA aging', 'bar', [
                    ['key' => 'on_track', 'label' => 'On track (>30d)', 'value' => $aging['on_track']],
                    ['key' => 'due_30', 'label' => 'Due ≤30 days', 'value' => $aging['due_30']],
                    ['key' => 'overdue', 'label' => 'Overdue', 'value' => $aging['overdue']],
                    ['key' => 'unknown', 'label' => 'No SLA date', 'value' => $aging['unknown']],
                ], 'Sites'),
                $this->chart('ms', 'By milestone', 'bar', $this->toChart($byMilestone, 10), 'Sites', 'horizontal'),
            ],
            tables: [
                $this->table('rfti', 'RFTI pipeline', [
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'milestone', 'label' => 'Milestone'],
                    ['key' => 'days_to_rfti', 'label' => 'Days to RFTI', 'format' => 'number'],
                    ['key' => 'bucket', 'label' => 'SLA'],
                    ['key' => 'status', 'label' => 'Status'],
                ], array_slice($rows, 0, 150)),
            ],
            links: [['label' => 'Tower Sites', 'href' => '/dynamic-entities/tower_sites']],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function saqMilestoneAging(): array
    {
        $byStatus = [];
        $rows = [];
        $sites = DynReportSupport::siteIndex();
        foreach (DynReportSupport::recordsForSlug('saq_trackers') as $row) {
            $values = $row->values_json ?? [];
            $status = (string) ($row->status ?? $values['status'] ?? $values['saq_status'] ?? 'Unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $site = $sites[$siteId]['values'] ?? [];
            $updated = DynReportSupport::parseDate($values['updated_at'] ?? $values['last_update'] ?? $row->updated_at);
            $age = $updated ? $updated->diffInDays(now()) : null;
            $rows[] = [
                'tracker' => (string) ($row->title ?? $values['name'] ?? ''),
                'site' => (string) ($site['site_name'] ?? $site['site_code'] ?? '—'),
                'status' => $status,
                'age_days' => $age,
                'region' => (string) ($site['region'] ?? '—'),
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('total', 'SAQ trackers', count($rows), 'number'),
                $this->kpi('statuses', 'Status buckets', count($byStatus), 'number'),
            ],
            charts: [
                $this->chart('status', 'SAQ by status', 'donut', $this->toChart($byStatus), 'Trackers'),
            ],
            tables: [
                $this->table('saq', 'SAQ milestone aging', [
                    ['key' => 'tracker', 'label' => 'Tracker'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'age_days', 'label' => 'Age (days)', 'format' => 'number'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [['label' => 'SAQ Trackers', 'href' => '/dynamic-entities/saq_trackers']],
            message: count($rows) === 0 ? 'No SAQ trackers found.' : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function permitStatusCompliance(): array
    {
        $byStatus = [];
        $rows = [];
        $sites = DynReportSupport::siteIndex();
        foreach (DynReportSupport::recordsForSlug('site_permits') as $row) {
            $values = $row->values_json ?? [];
            $status = (string) ($row->status ?? $values['status'] ?? $values['permit_status'] ?? 'Unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $site = $sites[$siteId]['values'] ?? [];
            $rows[] = [
                'permit' => (string) ($row->title ?? $values['name'] ?? $values['permit_type'] ?? ''),
                'site' => (string) ($site['site_name'] ?? $site['site_code'] ?? '—'),
                'status' => $status,
                'expiry' => (string) ($values['expiry_date'] ?? $values['valid_until'] ?? '—'),
                'region' => (string) ($site['region'] ?? '—'),
            ];
        }
        $approved = 0;
        foreach ($byStatus as $label => $count) {
            if (str_contains(strtolower($label), 'approv') || str_contains(strtolower($label), 'secur')) {
                $approved += $count;
            }
        }

        return $this->pack(
            kpis: [
                $this->kpi('total', 'Permits', count($rows), 'number'),
                $this->kpi('approved', 'Approved / secured', $approved, 'number'),
                $this->kpi('open', 'Other statuses', count($rows) - $approved, 'number'),
            ],
            charts: [
                $this->chart('status', 'Permit status mix', 'donut', $this->toChart($byStatus), 'Permits'),
            ],
            tables: [
                $this->table('permits', 'Permit status & compliance', [
                    ['key' => 'permit', 'label' => 'Permit'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'expiry', 'label' => 'Expiry'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [['label' => 'Site Permits', 'href' => '/dynamic-entities/site_permits']],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function energizationPowerStatus(): array
    {
        $byStatus = [];
        $rows = [];
        $sites = DynReportSupport::siteIndex();
        foreach (DynReportSupport::recordsForSlug('power_trackers') as $row) {
            $values = $row->values_json ?? [];
            $status = (string) ($row->status ?? $values['status'] ?? $values['power_status'] ?? 'Unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $site = $sites[$siteId]['values'] ?? [];
            $rows[] = [
                'tracker' => (string) ($row->title ?? $values['name'] ?? ''),
                'site' => (string) ($site['site_name'] ?? $site['site_code'] ?? '—'),
                'status' => $status,
                'utility' => (string) ($values['electric_utility'] ?? $values['utility_name'] ?? '—'),
                'energized_at' => (string) ($values['energized_date'] ?? $values['energization_date'] ?? '—'),
                'region' => (string) ($site['region'] ?? '—'),
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('total', 'Power trackers', count($rows), 'number'),
                $this->kpi('statuses', 'Status buckets', count($byStatus), 'number'),
            ],
            charts: [
                $this->chart('status', 'Energization status', 'donut', $this->toChart($byStatus), 'Trackers'),
            ],
            tables: [
                $this->table('power', 'Energization & power status', [
                    ['key' => 'tracker', 'label' => 'Tracker'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'utility', 'label' => 'Utility'],
                    ['key' => 'energized_at', 'label' => 'Energized'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [
                ['label' => 'Power Trackers', 'href' => '/dynamic-entities/power_trackers'],
                ['label' => 'Electric Utilities', 'href' => '/dynamic-entities/electric_utilities'],
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function colocationTenancy(): array
    {
        $byStatus = [];
        $rows = [];
        $sites = DynReportSupport::siteIndex();
        foreach (DynReportSupport::recordsForSlug('site_colocations') as $row) {
            $values = $row->values_json ?? [];
            $status = (string) ($row->status ?? $values['status'] ?? 'Active');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $site = $sites[$siteId]['values'] ?? [];
            $rows[] = [
                'colo' => (string) ($row->title ?? $values['name'] ?? ''),
                'site' => (string) ($site['site_name'] ?? $site['site_code'] ?? '—'),
                'tenant' => (string) ($values['tenant_name'] ?? $values['operator'] ?? $values['customer_name'] ?? '—'),
                'status' => $status,
                'start' => (string) ($values['start_date'] ?? $values['commencement_date'] ?? '—'),
                'rent' => round(DynReportSupport::money($values['monthly_rent'] ?? $values['rent_amount'] ?? 0), 2),
                'region' => (string) ($site['region'] ?? '—'),
            ];
        }
        foreach (DynReportSupport::recordsForSlug('telco_contracts') as $row) {
            $values = $row->values_json ?? [];
            $status = (string) ($row->status ?? $values['status'] ?? 'Active');
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $site = $sites[$siteId]['values'] ?? [];
            $rows[] = [
                'colo' => (string) ($row->title ?? $values['name'] ?? 'Telco contract'),
                'site' => (string) ($site['site_name'] ?? $site['site_code'] ?? '—'),
                'tenant' => (string) ($values['operator'] ?? $values['tenant_name'] ?? '—'),
                'status' => $status,
                'start' => (string) ($values['start_date'] ?? '—'),
                'rent' => round(DynReportSupport::money($values['monthly_rent'] ?? $values['amount'] ?? 0), 2),
                'region' => (string) ($site['region'] ?? '—'),
            ];
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
        }

        return $this->pack(
            kpis: [
                $this->kpi('tenancies', 'Tenancy rows', count($rows), 'number'),
                $this->kpi('rent', 'Listed rent total', array_sum(array_column($rows, 'rent')), 'money'),
            ],
            charts: [
                $this->chart('status', 'Tenancy status', 'donut', $this->toChart($byStatus), 'Contracts'),
            ],
            tables: [
                $this->table('colo', 'Colocation & tenancy', [
                    ['key' => 'colo', 'label' => 'Record'],
                    ['key' => 'site', 'label' => 'Site'],
                    ['key' => 'tenant', 'label' => 'Tenant / operator'],
                    ['key' => 'region', 'label' => 'Region'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'start', 'label' => 'Start'],
                    ['key' => 'rent', 'label' => 'Rent', 'format' => 'money'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [
                ['label' => 'Colocations', 'href' => '/dynamic-entities/site_colocations'],
                ['label' => 'Telco Contracts', 'href' => '/dynamic-entities/telco_contracts'],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function trialBalance(array $filters): array
    {
        $accounts = $this->accountBalances($filters);
        $debit = 0.0;
        $credit = 0.0;
        $rows = [];
        foreach ($accounts as $account) {
            $net = $account['debit'] - $account['credit'];
            $d = $net >= 0 ? $net : 0.0;
            $c = $net < 0 ? abs($net) : 0.0;
            $debit += $d;
            $credit += $c;
            $rows[] = [
                'code' => $account['code'],
                'name' => $account['name'],
                'classification' => $account['classification'],
                'debit' => round($d, 2),
                'credit' => round($c, 2),
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('debit', 'Total debit', $debit, 'money'),
                $this->kpi('credit', 'Total credit', $credit, 'money'),
                $this->kpi('diff', 'Difference', abs($debit - $credit), 'money'),
                $this->kpi('accounts', 'Accounts', count($rows), 'number'),
            ],
            charts: [],
            tables: [
                $this->table('tb', 'Trial balance', [
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'name', 'label' => 'Account'],
                    ['key' => 'classification', 'label' => 'Class'],
                    ['key' => 'debit', 'label' => 'Debit', 'format' => 'money'],
                    ['key' => 'credit', 'label' => 'Credit', 'format' => 'money'],
                ], $rows),
            ],
            links: [
                ['label' => 'General Ledger', 'href' => '/dynamic-entities/general_ledger'],
                ['label' => 'Chart of Accounts', 'href' => '/dynamic-entities/chart_of_accounts'],
            ],
            message: count($rows) === 0 ? 'No GL / CoA balances available yet.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function incomeStatement(array $filters): array
    {
        $accounts = $this->accountBalances($filters);
        $revenue = 0.0;
        $expense = 0.0;
        $rows = [];
        foreach ($accounts as $account) {
            $class = strtolower($account['classification']);
            $net = $account['credit'] - $account['debit'];
            if (str_contains($class, 'revenue') || str_contains($class, 'income')) {
                $revenue += $net;
                $rows[] = ['section' => 'Revenue', 'code' => $account['code'], 'name' => $account['name'], 'amount' => round($net, 2)];
            } elseif (str_contains($class, 'expense') || str_contains($class, 'cost') || str_contains($class, 'opex')) {
                $expense += ($account['debit'] - $account['credit']);
                $rows[] = ['section' => 'Expense', 'code' => $account['code'], 'name' => $account['name'], 'amount' => round($account['debit'] - $account['credit'], 2)];
            }
        }

        // Fallback: sales / opex entities when CoA classification is sparse
        if ($revenue == 0.0 && $expense == 0.0) {
            foreach (DynReportSupport::recordsForSlug('sales_transactions') as $row) {
                $revenue += DynReportSupport::money(($row->values_json ?? [])['total_amount'] ?? 0);
            }
            foreach (DynReportSupport::recordsForSlug('site_operating_costs') as $row) {
                $expense += DynReportSupport::money(($row->values_json ?? [])['amount'] ?? 0);
            }
            $rows = [
                ['section' => 'Revenue', 'code' => '—', 'name' => 'Sales transactions (aggregate)', 'amount' => round($revenue, 2)],
                ['section' => 'Expense', 'code' => '—', 'name' => 'Site operating costs (aggregate)', 'amount' => round($expense, 2)],
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('revenue', 'Revenue', $revenue, 'money'),
                $this->kpi('expense', 'Expenses', $expense, 'money'),
                $this->kpi('net', 'Net income', $revenue - $expense, 'money'),
            ],
            charts: [
                $this->chart('pnl', 'Revenue vs expense', 'bar', [
                    ['key' => 'rev', 'label' => 'Revenue', 'value' => round($revenue, 2)],
                    ['key' => 'exp', 'label' => 'Expenses', 'value' => round($expense, 2)],
                ], 'Amount'),
            ],
            tables: [
                $this->table('is', 'Income statement lines', [
                    ['key' => 'section', 'label' => 'Section'],
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'name', 'label' => 'Account'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
                ], $rows),
            ],
            links: [['label' => 'Trial Balance', 'href' => '/dynamic-entities/reports/trial-balance']],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function balanceSheet(array $filters): array
    {
        $accounts = $this->accountBalances($filters);
        $assets = 0.0;
        $liabilities = 0.0;
        $equity = 0.0;
        $rows = [];
        foreach ($accounts as $account) {
            $class = strtolower($account['classification']);
            $net = $account['debit'] - $account['credit'];
            if (str_contains($class, 'asset')) {
                $assets += $net;
                $rows[] = ['section' => 'Assets', 'code' => $account['code'], 'name' => $account['name'], 'amount' => round($net, 2)];
            } elseif (str_contains($class, 'liab')) {
                $amt = $account['credit'] - $account['debit'];
                $liabilities += $amt;
                $rows[] = ['section' => 'Liabilities', 'code' => $account['code'], 'name' => $account['name'], 'amount' => round($amt, 2)];
            } elseif (str_contains($class, 'equity') || str_contains($class, 'capital')) {
                $amt = $account['credit'] - $account['debit'];
                $equity += $amt;
                $rows[] = ['section' => 'Equity', 'code' => $account['code'], 'name' => $account['name'], 'amount' => round($amt, 2)];
            }
        }

        if ($assets == 0.0 && $liabilities == 0.0 && $equity == 0.0) {
            foreach (DynReportSupport::recordsForSlug('bank_accounts') as $row) {
                $assets += DynReportSupport::money(($row->values_json ?? [])['current_balance'] ?? 0);
            }
            foreach (DynReportSupport::recordsForSlug('fixed_assets') as $row) {
                $assets += DynReportSupport::money(($row->values_json ?? [])['acquisition_cost'] ?? 0);
            }
            $rows = [
                ['section' => 'Assets', 'code' => '—', 'name' => 'Bank + fixed assets (aggregate)', 'amount' => round($assets, 2)],
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('assets', 'Assets', $assets, 'money'),
                $this->kpi('liabilities', 'Liabilities', $liabilities, 'money'),
                $this->kpi('equity', 'Equity', $equity, 'money'),
                $this->kpi('check', 'Assets − L−E', $assets - $liabilities - $equity, 'money'),
            ],
            charts: [
                $this->chart('bs', 'Balance sheet mix', 'donut', [
                    ['key' => 'a', 'label' => 'Assets', 'value' => round(max($assets, 0), 2)],
                    ['key' => 'l', 'label' => 'Liabilities', 'value' => round(max($liabilities, 0), 2)],
                    ['key' => 'e', 'label' => 'Equity', 'value' => round(max($equity, 0), 2)],
                ], 'Amount'),
            ],
            tables: [
                $this->table('bs', 'Balance sheet', [
                    ['key' => 'section', 'label' => 'Section'],
                    ['key' => 'code', 'label' => 'Code'],
                    ['key' => 'name', 'label' => 'Account'],
                    ['key' => 'amount', 'label' => 'Amount', 'format' => 'money'],
                ], $rows),
            ],
            links: [['label' => 'Trial Balance', 'href' => '/dynamic-entities/reports/trial-balance']],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function cashFlow(array $filters): array
    {
        $dateFrom = DynReportSupport::parseDate($filters['date_from'] ?? null) ?? now()->startOfMonth();
        $dateTo = DynReportSupport::parseDate($filters['date_to'] ?? null) ?? now()->endOfMonth();
        $operatingIn = 0.0;
        $operatingOut = 0.0;
        $rows = [];

        foreach (DynReportSupport::recordsForSlug('payments_and_receipts') as $row) {
            $values = $row->values_json ?? [];
            $date = DynReportSupport::parseDate($values['payment_date'] ?? null);
            if ($date === null || $date->lt($dateFrom) || $date->gt($dateTo)) {
                continue;
            }
            $amount = DynReportSupport::money($values['amount'] ?? 0);
            $type = strtolower((string) ($values['type'] ?? ''));
            $isIn = str_contains($type, 'receipt') || str_contains($type, 'collection');
            if ($isIn) {
                $operatingIn += $amount;
            } else {
                $operatingOut += $amount;
            }
            $rows[] = [
                'date' => $date->toDateString(),
                'ref' => (string) ($values['payment_number'] ?? $row->title ?? ''),
                'type' => (string) ($values['type'] ?? ''),
                'description' => (string) ($values['description'] ?? ''),
                'inflow' => $isIn ? round($amount, 2) : 0.0,
                'outflow' => $isIn ? 0.0 : round($amount, 2),
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('in', 'Operating inflows', $operatingIn, 'money'),
                $this->kpi('out', 'Operating outflows', $operatingOut, 'money'),
                $this->kpi('net', 'Net operating cash', $operatingIn - $operatingOut, 'money'),
            ],
            charts: [
                $this->chart('cf', 'Cash flow', 'bar', [
                    ['key' => 'in', 'label' => 'Inflows', 'value' => round($operatingIn, 2)],
                    ['key' => 'out', 'label' => 'Outflows', 'value' => round($operatingOut, 2)],
                ], 'Amount'),
            ],
            tables: [
                $this->table('cf', 'Cash movements', [
                    ['key' => 'date', 'label' => 'Date'],
                    ['key' => 'ref', 'label' => 'Reference'],
                    ['key' => 'type', 'label' => 'Type'],
                    ['key' => 'description', 'label' => 'Description'],
                    ['key' => 'inflow', 'label' => 'Inflow', 'format' => 'money'],
                    ['key' => 'outflow', 'label' => 'Outflow', 'format' => 'money'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [['label' => 'Payments & Receipts', 'href' => '/dynamic-entities/payments_and_receipts']],
            filterOptions: [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function monthlyManagementAccounts(array $filters): array
    {
        $month = DynReportSupport::parseDate(($filters['month'] ?? null) ? ($filters['month'].'-01') : null)
            ?? now()->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        $sales = 0.0;
        $purchases = 0.0;
        $opex = 0.0;
        $payments = 0.0;

        foreach (DynReportSupport::recordsForSlug('sales_transactions') as $row) {
            $date = DynReportSupport::parseDate(($row->values_json ?? [])['transaction_date'] ?? null);
            if ($date !== null && ! $date->lt($from) && ! $date->gt($to)) {
                $sales += DynReportSupport::money(($row->values_json ?? [])['total_amount'] ?? 0);
            }
        }
        foreach (DynReportSupport::recordsForSlug('purchase_transactions') as $row) {
            $date = DynReportSupport::parseDate(($row->values_json ?? [])['purchase_date'] ?? ($row->values_json ?? [])['transaction_date'] ?? null);
            if ($date !== null && ! $date->lt($from) && ! $date->gt($to)) {
                $purchases += DynReportSupport::money(($row->values_json ?? [])['total_amount'] ?? 0);
            }
        }
        foreach (DynReportSupport::recordsForSlug('site_operating_costs') as $row) {
            $values = $row->values_json ?? [];
            $period = (string) ($values['period_month'] ?? '');
            $date = DynReportSupport::parseDate($values['cost_date'] ?? null);
            $inMonth = ($period !== '' && str_starts_with($period, $from->format('Y-m')))
                || ($date !== null && ! $date->lt($from) && ! $date->gt($to));
            if ($inMonth) {
                $opex += DynReportSupport::money($values['amount'] ?? 0);
            }
        }
        foreach (DynReportSupport::recordsForSlug('payments_and_receipts') as $row) {
            $date = DynReportSupport::parseDate(($row->values_json ?? [])['payment_date'] ?? null);
            if ($date !== null && ! $date->lt($from) && ! $date->gt($to)) {
                $payments += DynReportSupport::money(($row->values_json ?? [])['amount'] ?? 0);
            }
        }

        return $this->pack(
            kpis: [
                $this->kpi('sales', 'Sales', $sales, 'money'),
                $this->kpi('purchases', 'Purchases', $purchases, 'money'),
                $this->kpi('opex', 'Site opex', $opex, 'money'),
                $this->kpi('payments', 'Payments volume', $payments, 'money'),
                $this->kpi('contrib', 'Sales − purchases − opex', $sales - $purchases - $opex, 'money'),
            ],
            charts: [
                $this->chart('mma', 'Monthly management accounts', 'bar', [
                    ['key' => 'sales', 'label' => 'Sales', 'value' => round($sales, 2)],
                    ['key' => 'purchases', 'label' => 'Purchases', 'value' => round($purchases, 2)],
                    ['key' => 'opex', 'label' => 'Opex', 'value' => round($opex, 2)],
                ], 'Amount'),
            ],
            tables: [],
            links: [
                ['label' => 'Income Statement', 'href' => '/dynamic-entities/reports/income-statement'],
                ['label' => 'CAS Executive Dashboard', 'href' => '/dynamic-entities/reports/cas-executive-dashboard'],
            ],
            filterOptions: ['month' => $from->format('Y-m')],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function birCompliance(): array
    {
        $rows = [];
        $totalWithheld = 0.0;
        foreach (DynReportSupport::recordsForSlug('bir_form_2307_certificates') as $row) {
            $values = $row->values_json ?? [];
            $amount = DynReportSupport::money($values['tax_withheld'] ?? $values['amount'] ?? 0);
            $totalWithheld += $amount;
            $rows[] = [
                'certificate' => (string) ($row->title ?? $values['certificate_number'] ?? $values['control_number'] ?? ''),
                'payee' => (string) ($values['payee_name'] ?? $values['supplier_name'] ?? '—'),
                'period' => (string) ($values['period'] ?? $values['quarter'] ?? '—'),
                'status' => (string) ($row->status ?? $values['status'] ?? ''),
                'tax_withheld' => round($amount, 2),
            ];
        }

        return $this->pack(
            kpis: [
                $this->kpi('certs', '2307 certificates', count($rows), 'number'),
                $this->kpi('withheld', 'Tax withheld', $totalWithheld, 'money'),
            ],
            charts: [],
            tables: [
                $this->table('bir', 'BIR Form 2307 certificates', [
                    ['key' => 'certificate', 'label' => 'Certificate'],
                    ['key' => 'payee', 'label' => 'Payee'],
                    ['key' => 'period', 'label' => 'Period'],
                    ['key' => 'status', 'label' => 'Status'],
                    ['key' => 'tax_withheld', 'label' => 'Tax withheld', 'format' => 'money'],
                ], array_slice($rows, 0, 100)),
            ],
            links: [
                ['label' => 'BIR 2307 Certificates', 'href' => '/dynamic-entities/bir_form_2307_certificates'],
            ],
            message: count($rows) === 0 ? 'No BIR 2307 certificates imported yet.' : null,
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{code: string, name: string, classification: string, debit: float, credit: float}>
     */
    private function accountBalances(array $filters): array
    {
        $accounts = [];
        foreach (DynReportSupport::recordsForSlug('chart_of_accounts') as $row) {
            $values = $row->values_json ?? [];
            $accounts[$row->id] = [
                'code' => (string) ($values['account_code'] ?? $values['code'] ?? ''),
                'name' => (string) ($values['account_name'] ?? $row->title ?? ''),
                'classification' => (string) ($values['cost_classification'] ?? $values['account_type'] ?? $values['classification'] ?? ''),
                'debit' => 0.0,
                'credit' => 0.0,
            ];
        }

        $periodFilter = $this->nullableString($filters['period'] ?? null);
        foreach (DynReportSupport::recordsForSlug('general_ledger') as $row) {
            $values = $row->values_json ?? [];
            if ($periodFilter !== null) {
                $period = (string) ($values['period'] ?? $values['fiscal_period'] ?? $values['period_month'] ?? '');
                if ($period !== '' && ! str_contains($period, $periodFilter)) {
                    continue;
                }
            }
            $accountId = (string) ($values['account_id'] ?? '');
            if ($accountId === '' || ! isset($accounts[$accountId])) {
                if ($accountId === '') {
                    continue;
                }
                $accounts[$accountId] = [
                    'code' => DynReportSupport::shortId($accountId),
                    'name' => DynReportSupport::shortId($accountId),
                    'classification' => (string) ($values['cost_classification'] ?? ''),
                    'debit' => 0.0,
                    'credit' => 0.0,
                ];
            }
            $accounts[$accountId]['debit'] += DynReportSupport::money($values['debit_amount'] ?? 0);
            $accounts[$accountId]['credit'] += DynReportSupport::money($values['credit_amount'] ?? 0);
        }

        return array_values(array_filter(
            $accounts,
            fn (array $a): bool => abs($a['debit']) > 0.0001 || abs($a['credit']) > 0.0001 || $a['code'] !== '',
        ));
    }

    private function agingBucket(Carbon $due, Carbon $asOf): string
    {
        $days = $due->diffInDays($asOf, false);
        if ($days <= 0) {
            return 'current';
        }
        if ($days <= 30) {
            return '1_30';
        }
        if ($days <= 60) {
            return '31_60';
        }
        if ($days <= 90) {
            return '61_90';
        }

        return '90_plus';
    }

    /**
     * @param  array<string, float>  $buckets
     * @return list<array{key: string, label: string, value: float}>
     */
    private function agingChart(array $buckets): array
    {
        $labels = [
            'current' => 'Current',
            '1_30' => '1–30',
            '31_60' => '31–60',
            '61_90' => '61–90',
            '90_plus' => '90+',
        ];

        $out = [];
        foreach ($labels as $key => $label) {
            $out[] = ['key' => $key, 'label' => $label, 'value' => round($buckets[$key] ?? 0, 2)];
        }

        return $out;
    }

    /**
     * @param  array<string, int|float>  $map
     * @return list<array{key: string, label: string, value: float|int}>
     */
    private function toChart(array $map, int $limit = 20): array
    {
        return collect($map)
            ->map(fn (int|float $v, string $k): array => ['key' => $k, 'label' => $k, 'value' => $v])
            ->sortByDesc('value')
            ->values()
            ->take($limit)
            ->all();
    }

    /**
     * @param  list<array{key: string, label: string, value: float|int|string, format?: string, sub?: string|null}>  $kpis
     * @param  list<array<string, mixed>>  $charts
     * @param  list<array<string, mixed>>  $tables
     * @param  list<array{label: string, href: string}>  $links
     * @param  array<string, mixed>  $filterOptions
     * @return array<string, mixed>
     */
    private function pack(
        array $kpis,
        array $charts,
        array $tables,
        array $links = [],
        array $filterOptions = [],
        ?string $message = null,
    ): array {
        return [
            'message' => $message,
            'generated_at' => now()->toIso8601String(),
            'kpis' => $kpis,
            'charts' => $charts,
            'tables' => $tables,
            'links' => $links,
            'filter_options' => $filterOptions,
        ];
    }

    /**
     * @return array{key: string, label: string, value: float|int, format: string, sub: null}
     */
    private function kpi(string $key, string $label, float|int $value, string $format): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'value' => is_float($value) ? round($value, 2) : $value,
            'format' => $format,
            'sub' => null,
        ];
    }

    /**
     * @param  list<array{key: string, label: string, value: float|int}>  $data
     * @return array<string, mixed>
     */
    private function chart(string $id, string $title, string $type, array $data, string $valueLabel, string $layout = 'vertical'): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'type' => $type,
            'layout' => $layout,
            'data' => $data,
            'valueLabel' => $valueLabel,
        ];
    }

    /**
     * @param  list<array{key: string, label: string, format?: string}>  $columns
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function table(string $id, string $title, array $columns, array $rows): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === 'all') {
            return null;
        }

        return (string) $value;
    }
}
