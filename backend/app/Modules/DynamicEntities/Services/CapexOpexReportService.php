<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;

final class CapexOpexReportService
{
    /**
     * @param  array{region?: string|null, structure_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $sites = DynReportSupport::siteIndex();
        if ($sites === []) {
            return $this->empty('Tower Sites not imported yet.');
        }

        $regionFilter = $this->nullableString($filters['region'] ?? null);
        $structureFilter = $this->nullableString($filters['structure_type'] ?? null);

        $capexBySite = [];
        foreach (DynReportSupport::recordsForSlug('fixed_assets') as $asset) {
            $values = $asset->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            if (! DynReportSupport::isUuid($siteId)) {
                continue;
            }
            $capexBySite[$siteId] = ($capexBySite[$siteId] ?? 0) + DynReportSupport::money($values['acquisition_cost'] ?? 0);
        }

        // Supplement Capex from GL lines tagged Capex when fixed assets miss a site
        $coaClass = $this->accountClassificationMap();
        foreach (DynReportSupport::recordsForSlug('general_ledger') as $entry) {
            $values = $entry->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            if (! DynReportSupport::isUuid($siteId)) {
                continue;
            }
            $cls = (string) ($values['cost_classification'] ?? '');
            if ($cls === '' && isset($values['account_id'])) {
                $cls = $coaClass[(string) $values['account_id']]['classification'] ?? '';
            }
            if (strcasecmp($cls, 'Capex') !== 0) {
                continue;
            }
            if (isset($capexBySite[$siteId]) && $capexBySite[$siteId] > 0) {
                continue;
            }
            $capexBySite[$siteId] = ($capexBySite[$siteId] ?? 0)
                + DynReportSupport::money($values['debit_amount'] ?? 0)
                - DynReportSupport::money($values['credit_amount'] ?? 0);
        }

        $opexBySite = [];
        $opexByCategory = [];
        $months = [];
        foreach (DynReportSupport::recordsForSlug('site_operating_costs') as $cost) {
            $values = $cost->values_json ?? [];
            $siteId = (string) ($values['tower_site_id'] ?? '');
            $amount = DynReportSupport::money($values['amount'] ?? 0);
            $category = (string) ($values['cost_category'] ?? 'Other');
            $period = (string) ($values['period_month'] ?? '');
            if ($period !== '') {
                $months[$period] = true;
            }
            if (DynReportSupport::isUuid($siteId)) {
                $opexBySite[$siteId] = ($opexBySite[$siteId] ?? 0) + $amount;
            }
            $opexByCategory[$category] = ($opexByCategory[$category] ?? 0) + $amount;
        }

        $monthCount = max(1, count($months));

        $byStructure = [];
        $byRegion = [];
        $sitesInScope = 0;
        $totalCapex = 0.0;
        $totalOpex = 0.0;
        $capexSites = 0;
        $opexSites = 0;

        foreach ($sites as $siteId => $site) {
            $values = $site['values'];
            $region = (string) ($values['region'] ?? '—');
            $structure = (string) ($values['tower_type'] ?? $values['project_type'] ?? '(unspecified)');
            if ($regionFilter !== null && strcasecmp($region, $regionFilter) !== 0) {
                continue;
            }
            if ($structureFilter !== null && strcasecmp($structure, $structureFilter) !== 0) {
                continue;
            }

            $sitesInScope++;
            $capex = $capexBySite[$siteId] ?? 0.0;
            $opex = $opexBySite[$siteId] ?? 0.0;
            $totalCapex += $capex;
            $totalOpex += $opex;
            if ($capex > 0) {
                $capexSites++;
            }
            if ($opex > 0) {
                $opexSites++;
            }

            if (! isset($byStructure[$structure])) {
                $byStructure[$structure] = ['structure' => $structure, 'sites' => 0, 'total_capex' => 0.0];
            }
            $byStructure[$structure]['sites']++;
            $byStructure[$structure]['total_capex'] += $capex;

            if (! isset($byRegion[$region])) {
                $byRegion[$region] = [
                    'region' => $region,
                    'sites' => 0,
                    'total_capex' => 0.0,
                    'total_opex' => 0.0,
                ];
            }
            $byRegion[$region]['sites']++;
            $byRegion[$region]['total_capex'] += $capex;
            $byRegion[$region]['total_opex'] += $opex;
        }

        $avgCapexBars = collect($byStructure)
            ->map(function (array $row): array {
                $avg = $row['sites'] > 0 ? $row['total_capex'] / $row['sites'] : 0.0;

                return [
                    'key' => $row['structure'],
                    'label' => $row['structure'] !== '' ? $row['structure'] : '(unspecified)',
                    'value' => round($avg, 2),
                ];
            })
            ->sortByDesc('value')
            ->values()
            ->all();

        $opexMix = collect($opexByCategory)
            ->map(fn (float $total, string $cat): array => [
                'key' => $cat,
                'label' => $cat !== '' ? $cat : 'Other',
                'value' => round($total, 2),
            ])
            ->sortByDesc('value')
            ->values()
            ->all();

        $regionRows = collect($byRegion)
            ->map(function (array $row): array {
                $sites = max(1, $row['sites']);

                return [
                    'region' => $row['region'],
                    'sites' => $row['sites'],
                    'total_capex' => round($row['total_capex'], 2),
                    'avg_capex' => round($row['total_capex'] / $sites, 2),
                    'total_opex' => round($row['total_opex'], 2),
                    'avg_opex' => round($row['total_opex'] / $sites, 2),
                ];
            })
            ->sortBy('region')
            ->values()
            ->all();

        $regions = collect($sites)->map(fn (array $s): string => (string) ($s['values']['region'] ?? ''))->filter()->unique()->sort()->values()->all();
        $structures = collect($sites)->map(fn (array $s): string => (string) ($s['values']['tower_type'] ?? $s['values']['project_type'] ?? ''))->filter()->unique()->sort()->values()->all();

        return [
            'message' => null,
            'kpis' => [
                'sites_in_scope' => $sitesInScope,
                'total_capex' => round($totalCapex, 2),
                'capex_sites' => $capexSites,
                'avg_capex' => $capexSites > 0 ? round($totalCapex / $capexSites, 2) : 0.0,
                'total_opex' => round($totalOpex, 2),
                'opex_sites' => $opexSites,
                'avg_opex' => $opexSites > 0 ? round($totalOpex / $opexSites, 2) : 0.0,
                'avg_opex_per_month' => $opexSites > 0 ? round(($totalOpex / $opexSites) / $monthCount, 2) : 0.0,
                'opex_months' => $monthCount,
            ],
            'avg_capex_by_structure' => $avgCapexBars,
            'monthly_opex_mix' => $opexMix,
            'averages_by_region' => $regionRows,
            'definitions' => [
                'capex' => array_values(array_unique(array_filter(array_map(
                    fn (array $a): string => $a['category'],
                    array_filter($coaClass, fn (array $a): bool => strcasecmp($a['classification'], 'Capex') === 0)
                )))),
                'opex' => array_values(array_unique(array_filter(array_map(
                    fn (array $a): string => $a['category'],
                    array_filter($coaClass, fn (array $a): bool => strcasecmp($a['classification'], 'Opex') === 0)
                )))),
            ],
            'filter_options' => [
                'regions' => $regions,
                'structure_types' => $structures,
            ],
        ];
    }

    /**
     * @return array<string, array{classification: string, category: string}>
     */
    private function accountClassificationMap(): array
    {
        $map = [];
        foreach (DynReportSupport::recordsForSlug('chart_of_accounts') as $account) {
            $values = $account->values_json ?? [];
            $map[$account->id] = [
                'classification' => (string) ($values['cost_classification'] ?? ''),
                'category' => (string) ($values['cost_category'] ?? $values['account_name'] ?? ''),
            ];
        }

        return $map;
    }

    /**
     * @return array<string, mixed>
     */
    private function empty(string $message): array
    {
        return [
            'message' => $message,
            'kpis' => [
                'sites_in_scope' => 0,
                'total_capex' => 0,
                'capex_sites' => 0,
                'avg_capex' => 0,
                'total_opex' => 0,
                'opex_sites' => 0,
                'avg_opex' => 0,
                'avg_opex_per_month' => 0,
                'opex_months' => 0,
            ],
            'avg_capex_by_structure' => [],
            'monthly_opex_mix' => [],
            'averages_by_region' => [],
            'definitions' => ['capex' => [], 'opex' => []],
            'filter_options' => ['regions' => [], 'structure_types' => []],
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
