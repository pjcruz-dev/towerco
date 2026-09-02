<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;

final class CostVsBudgetReportService
{
    /**
     * @param  array{region?: string|null, project_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $sites = DynReportSupport::siteIndex();
        $regionFilter = $this->nullableString($filters['region'] ?? null);
        $typeFilter = $this->nullableString($filters['project_type'] ?? null);

        $budgetBySite = [];
        $actualBySite = [];
        $committedBySite = [];
        $opexBySite = [];

        foreach (DynReportSupport::recordsForSlug('construction_projects') as $project) {
            $values = $project->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            if (! DynReportSupport::isUuid($siteId)) {
                continue;
            }
            $budgetBySite[$siteId] = ($budgetBySite[$siteId] ?? 0) + DynReportSupport::money($values['budget'] ?? 0);
            $actualBySite[$siteId] = ($actualBySite[$siteId] ?? 0) + DynReportSupport::money($values['total_cost'] ?? 0);
        }

        foreach (DynReportSupport::recordsForSlug('purchase_transactions') as $purchase) {
            $values = $purchase->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            if (! DynReportSupport::isUuid($siteId)) {
                continue;
            }
            $committedBySite[$siteId] = ($committedBySite[$siteId] ?? 0) + DynReportSupport::money($values['total_amount'] ?? 0);
        }

        foreach (DynReportSupport::recordsForSlug('site_operating_costs') as $cost) {
            $values = $cost->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            if (! DynReportSupport::isUuid($siteId)) {
                continue;
            }
            $opexBySite[$siteId] = ($opexBySite[$siteId] ?? 0) + DynReportSupport::money($values['amount'] ?? 0);
        }

        // Cost category spend from purchases / GL for donut
        $byCategory = [];
        foreach (DynReportSupport::recordsForSlug('purchase_transactions') as $purchase) {
            $values = $purchase->values_json ?? [];
            $cat = (string) ($values['cost_category'] ?? 'Uncategorized');
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + DynReportSupport::money($values['total_amount'] ?? 0);
        }
        foreach (DynReportSupport::recordsForSlug('fixed_assets') as $asset) {
            $values = $asset->values_json ?? [];
            $cat = (string) ($values['category'] ?? 'Fixed Assets');
            $byCategory[$cat] = ($byCategory[$cat] ?? 0) + DynReportSupport::money($values['acquisition_cost'] ?? 0);
        }

        $rows = [];
        $byRegion = [];
        $totalBudget = 0.0;
        $totalCommitted = 0.0;
        $totalActual = 0.0;
        $totalOpex = 0.0;
        $towerCount = 0;

        foreach ($sites as $siteId => $site) {
            $values = $site['values'];
            $region = (string) ($values['region'] ?? '—');
            $projectType = (string) ($values['project_type'] ?? '—');
            if ($regionFilter !== null && strcasecmp($region, $regionFilter) !== 0) {
                continue;
            }
            if ($typeFilter !== null && strcasecmp($projectType, $typeFilter) !== 0) {
                continue;
            }

            $budget = $budgetBySite[$siteId] ?? 0.0;
            $committed = $committedBySite[$siteId] ?? 0.0;
            $actual = $actualBySite[$siteId] ?? 0.0;
            $opex = $opexBySite[$siteId] ?? 0.0;
            $variance = $budget - $actual;
            $utilisation = $budget > 0 ? ($actual / $budget) * 100 : 0.0;

            $towerCount++;
            $totalBudget += $budget;
            $totalCommitted += $committed;
            $totalActual += $actual;
            $totalOpex += $opex;

            if (! isset($byRegion[$region])) {
                $byRegion[$region] = ['region' => $region, 'budget' => 0.0, 'committed' => 0.0, 'actual' => 0.0];
            }
            $byRegion[$region]['budget'] += $budget;
            $byRegion[$region]['committed'] += $committed;
            $byRegion[$region]['actual'] += $actual;

            $rows[] = [
                'site_id' => $siteId,
                'site_code' => (string) ($values['site_code'] ?? $site['source_external_id'] ?? ''),
                'site_name' => (string) ($values['site_name'] ?? $site['title'] ?? ''),
                'region' => $region,
                'type' => $projectType,
                'budget' => round($budget, 2),
                'committed' => round($committed, 2),
                'actual' => round($actual, 2),
                'variance' => round($variance, 2),
                'utilisation' => round($utilisation, 1),
            ];
        }

        // Fill actual from fixed assets when construction actual is 0
        $assetActual = [];
        foreach (DynReportSupport::recordsForSlug('fixed_assets') as $asset) {
            $values = $asset->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            if (! DynReportSupport::isUuid($siteId)) {
                continue;
            }
            $assetActual[$siteId] = ($assetActual[$siteId] ?? 0) + DynReportSupport::money($values['acquisition_cost'] ?? 0);
        }
        $totalActual = 0.0;
        foreach ($rows as &$row) {
            if ($row['actual'] <= 0 && isset($assetActual[$row['site_id']])) {
                $row['actual'] = round($assetActual[$row['site_id']], 2);
                $row['variance'] = round($row['budget'] - $row['actual'], 2);
                $row['utilisation'] = $row['budget'] > 0 ? round(($row['actual'] / $row['budget']) * 100, 1) : 0.0;
            }
            $totalActual += $row['actual'];
            $region = $row['region'];
            if (isset($byRegion[$region])) {
                // rebuild region actual from rows later
            }
        }
        unset($row);

        // Rebuild region actuals from final rows
        foreach ($byRegion as $region => $_) {
            $byRegion[$region]['actual'] = 0.0;
            $byRegion[$region]['budget'] = 0.0;
            $byRegion[$region]['committed'] = 0.0;
        }
        foreach ($rows as $row) {
            $region = $row['region'];
            if (! isset($byRegion[$region])) {
                $byRegion[$region] = ['region' => $region, 'budget' => 0.0, 'committed' => 0.0, 'actual' => 0.0];
            }
            $byRegion[$region]['budget'] += $row['budget'];
            $byRegion[$region]['committed'] += $row['committed'];
            $byRegion[$region]['actual'] += $row['actual'];
        }

        usort($rows, static fn (array $a, array $b): int => strcmp($a['site_code'], $b['site_code']));

        $pctOfBudget = $totalBudget > 0 ? round(($totalActual / $totalBudget) * 100) : 0;
        $variance = $totalBudget - $totalActual;

        return [
            'message' => $sites === [] ? 'Tower Sites not imported yet.' : null,
            'kpis' => [
                'towers' => $towerCount,
                'budgeted' => round($totalBudget, 2),
                'committed' => round($totalCommitted, 2),
                'actual' => round($totalActual, 2),
                'actual_pct_of_budget' => $pctOfBudget,
                'variance' => round($variance, 2),
                'under_budget' => $variance >= 0,
                'opex_to_date' => round($totalOpex, 2),
            ],
            'by_region' => collect($byRegion)
                ->map(fn (array $r): array => [
                    'region' => $r['region'],
                    'budget' => round($r['budget'], 2),
                    'committed' => round($r['committed'], 2),
                    'actual' => round($r['actual'], 2),
                ])
                ->sortBy('region')
                ->values()
                ->all(),
            'by_category' => collect($byCategory)
                ->map(fn (float $v, string $k): array => [
                    'key' => $k,
                    'label' => $k !== '' ? $k : 'Uncategorized',
                    'value' => round($v, 2),
                ])
                ->sortByDesc('value')
                ->values()
                ->take(12)
                ->all(),
            'register' => array_slice($rows, 0, 500),
            'filter_options' => [
                'regions' => collect($sites)->map(fn (array $s): string => (string) ($s['values']['region'] ?? ''))->filter()->unique()->sort()->values()->all(),
                'project_types' => collect($sites)->map(fn (array $s): string => (string) ($s['values']['project_type'] ?? ''))->filter()->unique()->sort()->values()->all(),
            ],
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
