<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;

/**
 * Cross-pack ATC executive KPIs over dyn_records (sites, build, procurement, finance).
 */
final class AtcExecutiveDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $sites = DynReportSupport::siteIndex();
        $siteCount = count($sites);

        if ($siteCount === 0) {
            return $this->empty('Import Phase 1 tower sites to populate the executive dashboard.');
        }

        $byRegion = [];
        $byProjectType = [];
        $bySiteStatus = [];
        $byMilestone = [];
        $rftiAging = ['on_track' => 0, 'due_30' => 0, 'overdue' => 0, 'unknown' => 0];
        $wip = 0;
        $rftiReady = 0;

        foreach ($sites as $site) {
            $values = $site['values'];
            $region = (string) ($values['region'] ?? '—');
            $projectType = (string) ($values['project_type'] ?? '—');
            $siteStatus = (string) ($values['site_status'] ?? $site['status'] ?? 'Unknown');
            $milestone = (string) ($values['milestone'] ?? $values['project_milestone'] ?? 'Unassigned');

            $byRegion[$region] = ($byRegion[$region] ?? 0) + 1;
            $byProjectType[$projectType] = ($byProjectType[$projectType] ?? 0) + 1;
            $bySiteStatus[$siteStatus] = ($bySiteStatus[$siteStatus] ?? 0) + 1;
            $byMilestone[$milestone] = ($byMilestone[$milestone] ?? 0) + 1;

            $statusLower = strtolower($siteStatus.' '.$milestone);
            if (str_contains($statusLower, 'wip') || str_contains($statusLower, 'progress') || str_contains($statusLower, 'acquisition')) {
                $wip++;
            }
            if (str_contains($statusLower, 'rfti') || str_contains($statusLower, 'rfi')) {
                $rftiReady++;
            }

            $days = $values['days_to_rfti'] ?? null;
            if ($days === null || $days === '') {
                $rftiAging['unknown']++;
            } elseif (is_numeric($days)) {
                $d = (float) $days;
                if ($d < 0) {
                    $rftiAging['overdue']++;
                } elseif ($d <= 30) {
                    $rftiAging['due_30']++;
                } else {
                    $rftiAging['on_track']++;
                }
            } else {
                $rftiAging['unknown']++;
            }
        }

        $construction = $this->constructionSummary();
        $procurement = $this->procurementSummary();
        $finance = $this->financeSummary();
        $attention = $this->attentionItems($sites, $rftiAging, $procurement);

        return [
            'message' => null,
            'generated_at' => now()->toIso8601String(),
            'kpis' => [
                'sites_total' => $siteCount,
                'sites_wip' => $wip,
                'sites_rfti_ready' => $rftiReady,
                'construction_active' => $construction['active'],
                'construction_completed' => $construction['completed'],
                'open_procurement_requests' => $procurement['open_requests'],
                'purchases_mtd' => $procurement['purchases_mtd'],
                'capex_booked' => $finance['capex'],
                'opex_ytd' => $finance['opex'],
                'sales_mtd' => $finance['sales_mtd'],
            ],
            'portfolio' => [
                'by_region' => $this->toChart($byRegion),
                'by_project_type' => $this->toChart($byProjectType),
                'by_site_status' => $this->toChart($bySiteStatus, 12),
                'by_milestone' => $this->toChart($byMilestone, 10),
            ],
            'rfti_pipeline' => [
                'aging' => [
                    ['key' => 'on_track', 'label' => 'On track (>30d)', 'value' => $rftiAging['on_track']],
                    ['key' => 'due_30', 'label' => 'Due ≤30 days', 'value' => $rftiAging['due_30']],
                    ['key' => 'overdue', 'label' => 'Overdue', 'value' => $rftiAging['overdue']],
                    ['key' => 'unknown', 'label' => 'No SLA date', 'value' => $rftiAging['unknown']],
                ],
                'by_milestone' => $this->toChart($byMilestone, 8),
            ],
            'construction' => $construction,
            'procurement' => $procurement,
            'finance' => $finance,
            'attention' => $attention,
            'quick_links' => [
                ['label' => 'Tower Sites', 'href' => '/dynamic-entities/tower_sites'],
                ['label' => 'Ticketing Board', 'href' => '/dynamic-entities/ticketing-board'],
                ['label' => 'CAS Executive', 'href' => '/dynamic-entities/reports/cas-executive-dashboard'],
                ['label' => 'RFTI Pipeline', 'href' => '/dynamic-entities/reports/rfti-pipeline'],
                ['label' => 'Stock on Hand', 'href' => '/dynamic-entities/reports/stock-on-hand'],
                ['label' => 'Materials Issued', 'href' => '/dynamic-entities/reports/materials-issued'],
                ['label' => 'Cost vs Budget', 'href' => '/dynamic-entities/reports/cost-vs-budget'],
                ['label' => 'Capex & Opex', 'href' => '/dynamic-entities/reports/capex-opex'],
                ['label' => 'AR & AP Aging', 'href' => '/dynamic-entities/reports/ar-ap-aging'],
                ['label' => 'Purchase Monitoring', 'href' => '/dynamic-entities/reports/purchase-monitoring'],
                ['label' => 'Daily Sales', 'href' => '/dynamic-entities/reports/daily-sales'],
            ],
        ];
    }

    /**
     * @return array{active: int, completed: int, total_budget: float, total_actual: float, by_status: list<array{key: string, label: string, value: int}>}
     */
    private function constructionSummary(): array
    {
        $active = 0;
        $completed = 0;
        $budget = 0.0;
        $actual = 0.0;
        $byStatus = [];

        foreach (DynReportSupport::recordsForSlug('construction_projects') as $project) {
            $values = $project->values_json ?? [];
            $status = (string) ($project->status ?? $values['status'] ?? $values['cme_status'] ?? 'Unknown');
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;
            $budget += DynReportSupport::money($values['budget'] ?? 0);
            $actual += DynReportSupport::money($values['total_cost'] ?? 0);
            $pct = DynReportSupport::money($values['percentage_in_progress'] ?? 0);
            if ($pct >= 100 || str_contains(strtolower($status), 'complete') || str_contains(strtolower($status), 'done')) {
                $completed++;
            } else {
                $active++;
            }
        }

        return [
            'active' => $active,
            'completed' => $completed,
            'total_budget' => round($budget, 2),
            'total_actual' => round($actual, 2),
            'by_status' => $this->toChart($byStatus, 8),
        ];
    }

    /**
     * @return array{open_requests: int, purchases_mtd: float, purchase_count_mtd: int, suppliers_active: int}
     */
    private function procurementSummary(): array
    {
        $open = 0;
        foreach (DynReportSupport::recordsForSlug('procurement_requests') as $pr) {
            $status = strtolower((string) ($pr->status ?? ($pr->values_json['status'] ?? '')));
            if ($status === '' || str_contains($status, 'open') || str_contains($status, 'pending') || str_contains($status, 'draft') || str_contains($status, 'approved')) {
                if (! str_contains($status, 'closed') && ! str_contains($status, 'cancel') && ! str_contains($status, 'complete')) {
                    $open++;
                }
            }
        }

        $start = now()->startOfMonth();
        $purchasesMtd = 0.0;
        $countMtd = 0;
        $suppliers = [];
        foreach (DynReportSupport::recordsForSlug('purchase_transactions') as $purchase) {
            $values = $purchase->values_json ?? [];
            $date = DynReportSupport::parseDate($values['purchase_date'] ?? null);
            if ($date === null || $date->lt($start)) {
                continue;
            }
            $purchasesMtd += DynReportSupport::money($values['total_amount'] ?? 0);
            $countMtd++;
            $sid = (string) ($values['supplier_id'] ?? '');
            if ($sid !== '') {
                $suppliers[$sid] = true;
            }
        }

        return [
            'open_requests' => $open,
            'purchases_mtd' => round($purchasesMtd, 2),
            'purchase_count_mtd' => $countMtd,
            'suppliers_active' => count($suppliers),
        ];
    }

    /**
     * @return array{capex: float, opex: float, sales_mtd: float, sales_count_mtd: int}
     */
    private function financeSummary(): array
    {
        $capex = 0.0;
        foreach (DynReportSupport::recordsForSlug('fixed_assets') as $asset) {
            $capex += DynReportSupport::money(($asset->values_json ?? [])['acquisition_cost'] ?? 0);
        }

        $opex = 0.0;
        $yearStart = now()->startOfYear();
        foreach (DynReportSupport::recordsForSlug('site_operating_costs') as $cost) {
            $values = $cost->values_json ?? [];
            $period = DynReportSupport::parseDate($values['period_month'] ?? null);
            if ($period !== null && $period->lt($yearStart)) {
                continue;
            }
            $opex += DynReportSupport::money($values['amount'] ?? 0);
        }

        $salesMtd = 0.0;
        $salesCount = 0;
        $monthStart = now()->startOfMonth();
        foreach (DynReportSupport::recordsForSlug('sales_transactions') as $sale) {
            $values = $sale->values_json ?? [];
            $date = DynReportSupport::parseDate($values['transaction_date'] ?? null);
            if ($date === null || $date->lt($monthStart)) {
                continue;
            }
            $salesMtd += DynReportSupport::money($values['total_amount'] ?? 0);
            $salesCount++;
        }

        return [
            'capex' => round($capex, 2),
            'opex' => round($opex, 2),
            'sales_mtd' => round($salesMtd, 2),
            'sales_count_mtd' => $salesCount,
        ];
    }

    /**
     * @param  array<string, array{id: string, title: string|null, values: array<string, mixed>, status: string|null}>  $sites
     * @param  array{on_track: int, due_30: int, overdue: int, unknown: int}  $rftiAging
     * @param  array{open_requests: int, purchases_mtd: float, purchase_count_mtd: int, suppliers_active: int}  $procurement
     * @return list<array{severity: string, detail: string, href: string, tone: string}>
     */
    private function attentionItems(array $sites, array $rftiAging, array $procurement): array
    {
        $items = [];

        if ($rftiAging['overdue'] > 0) {
            $items[] = [
                'severity' => 'RFTI SLA overdue',
                'detail' => $rftiAging['overdue'].' site(s) past RFTI target days.',
                'href' => '/dynamic-entities/tower_sites',
                'tone' => 'danger',
            ];
        }
        if ($rftiAging['due_30'] > 0) {
            $items[] = [
                'severity' => 'RFTI due within 30 days',
                'detail' => $rftiAging['due_30'].' site(s) approaching RFTI.',
                'href' => '/dynamic-entities/tower_sites',
                'tone' => 'warning',
            ];
        }
        if ($procurement['open_requests'] > 0) {
            $items[] = [
                'severity' => 'Open procurement requests',
                'detail' => $procurement['open_requests'].' request(s) still active.',
                'href' => '/dynamic-entities/procurement_requests',
                'tone' => 'neutral',
            ];
        }

        $saqAging = 0;
        foreach ($sites as $site) {
            $milestone = strtolower((string) (($site['values']['milestone'] ?? '') ?: ($site['values']['project_milestone'] ?? '')));
            if (str_contains($milestone, 'acquisition') || str_contains($milestone, 'saq')) {
                $saqAging++;
            }
        }
        if ($saqAging > 0) {
            $items[] = [
                'severity' => 'Sites in site acquisition',
                'detail' => $saqAging.' site(s) currently tagged SAQ / acquisition.',
                'href' => '/dynamic-entities/saq_trackers',
                'tone' => 'neutral',
            ];
        }

        if ($items === []) {
            $items[] = [
                'severity' => 'No critical alerts',
                'detail' => 'Portfolio SLAs and procurement queues look clear.',
                'href' => '/dynamic-entities/reports/capex-opex',
                'tone' => 'success',
            ];
        }

        return array_slice($items, 0, 6);
    }

    /**
     * @param  array<string, int|float>  $counts
     * @return list<array{key: string, label: string, value: float|int}>
     */
    private function toChart(array $counts, int $limit = 15): array
    {
        arsort($counts);

        return collect($counts)
            ->take($limit)
            ->map(fn (int|float $value, string $label): array => [
                'key' => $label !== '' ? $label : '—',
                'label' => $label !== '' ? $label : '—',
                'value' => $value,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $message): array
    {
        return [
            'message' => $message,
            'generated_at' => now()->toIso8601String(),
            'kpis' => [
                'sites_total' => 0,
                'sites_wip' => 0,
                'sites_rfti_ready' => 0,
                'construction_active' => 0,
                'construction_completed' => 0,
                'open_procurement_requests' => 0,
                'purchases_mtd' => 0,
                'capex_booked' => 0,
                'opex_ytd' => 0,
                'sales_mtd' => 0,
            ],
            'portfolio' => [
                'by_region' => [],
                'by_project_type' => [],
                'by_site_status' => [],
                'by_milestone' => [],
            ],
            'rfti_pipeline' => [
                'aging' => [],
                'by_milestone' => [],
            ],
            'construction' => [
                'active' => 0,
                'completed' => 0,
                'total_budget' => 0,
                'total_actual' => 0,
                'by_status' => [],
            ],
            'procurement' => [
                'open_requests' => 0,
                'purchases_mtd' => 0,
                'purchase_count_mtd' => 0,
                'suppliers_active' => 0,
            ],
            'finance' => [
                'capex' => 0,
                'opex' => 0,
                'sales_mtd' => 0,
                'sales_count_mtd' => 0,
            ],
            'attention' => [],
            'quick_links' => [],
        ];
    }
}
