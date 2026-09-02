<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Support\DynReportSupport;

final class MaterialsIssuedReportService
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null, region?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $sites = DynReportSupport::siteIndex();
        $products = DynReportSupport::titleMap('products', ['product_name', 'name', 'description']);
        $warehouses = DynReportSupport::titleMap('warehouses', ['warehouse_name', 'name']);

        $dateFrom = DynReportSupport::parseDate($filters['date_from'] ?? null);
        $dateTo = DynReportSupport::parseDate($filters['date_to'] ?? null);
        $regionFilter = $this->nullableString($filters['region'] ?? null);

        $issued = 0.0;
        $returned = 0.0;
        $towers = [];
        $byRegion = [];
        $byCategory = [];
        $byItem = [];
        $byWarehouse = [];
        $itemLines = 0;

        foreach (DynReportSupport::recordsForSlug('stock_movements') as $movement) {
            $values = $movement->values_json ?? [];
            $date = DynReportSupport::parseDate($values['movement_date'] ?? null);
            if ($dateFrom !== null && ($date === null || $date->lt($dateFrom))) {
                continue;
            }
            if ($dateTo !== null && ($date === null || $date->gt($dateTo))) {
                continue;
            }

            $siteId = (string) ($values['tower_site_id'] ?? '');
            $region = '—';
            if (DynReportSupport::isUuid($siteId) && isset($sites[$siteId])) {
                $region = (string) ($sites[$siteId]['values']['region'] ?? '—');
            }
            if ($regionFilter !== null && strcasecmp($region, $regionFilter) !== 0) {
                continue;
            }

            $movementType = strtolower((string) ($values['movement_type'] ?? $values['type'] ?? ''));
            $cost = DynReportSupport::money($values['total_cost'] ?? 0);
            $qty = DynReportSupport::money($values['quantity'] ?? 0);
            $productId = (string) ($values['product_id'] ?? '');
            $warehouseId = (string) ($values['warehouse_id'] ?? '');
            $productName = $products[$productId] ?? (DynReportSupport::isUuid($productId) ? DynReportSupport::shortId($productId) : '(no description)');
            $warehouseName = $warehouses[$warehouseId] ?? (DynReportSupport::isUuid($warehouseId) ? DynReportSupport::shortId($warehouseId) : '—');

            $isIssue = str_contains($movementType, 'issue');
            $isReturn = str_contains($movementType, 'return');

            if (! $isIssue && ! $isReturn) {
                continue;
            }

            $itemLines++;
            if (DynReportSupport::isUuid($siteId)) {
                $towers[$siteId] = true;
            }

            if ($isIssue) {
                $issued += $cost;
                $byRegion[$region] = ($byRegion[$region] ?? 0) + $cost;
                $cat = (string) ($values['notes'] ?? 'Materials');
                // Prefer product name as category bucket when notes empty
                if ($cat === '' || $cat === 'Materials') {
                    $cat = 'General Materials';
                }
                $byCategory[$cat] = ($byCategory[$cat] ?? 0) + $cost;

                if (! isset($byItem[$productName])) {
                    $byItem[$productName] = ['item' => $productName, 'qty' => 0.0, 'towers' => [], 'value' => 0.0];
                }
                $byItem[$productName]['qty'] += $qty;
                $byItem[$productName]['value'] += $cost;
                if (DynReportSupport::isUuid($siteId)) {
                    $byItem[$productName]['towers'][$siteId] = true;
                }

                if (! isset($byWarehouse[$warehouseName])) {
                    $byWarehouse[$warehouseName] = ['warehouse' => $warehouseName, 'slips' => 0, 'towers' => [], 'value' => 0.0];
                }
                $byWarehouse[$warehouseName]['slips']++;
                $byWarehouse[$warehouseName]['value'] += $cost;
                if (DynReportSupport::isUuid($siteId)) {
                    $byWarehouse[$warehouseName]['towers'][$siteId] = true;
                }
            }

            if ($isReturn) {
                $returned += $cost;
            }
        }

        $net = $issued - $returned;
        $towerCount = count($towers);

        return [
            'message' => null,
            'kpis' => [
                'towers_supplied' => $towerCount,
                'issued' => round($issued, 2),
                'returned' => round($returned, 2),
                'net_material_cost' => round($net, 2),
                'average_per_tower' => $towerCount > 0 ? round($net / $towerCount, 2) : 0.0,
                'item_lines' => $itemLines,
            ],
            'by_region' => collect($byRegion)
                ->map(fn (float $v, string $k): array => [
                    'key' => $k,
                    'label' => $k,
                    'value' => round($v, 2),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'by_category' => collect($byCategory)
                ->map(fn (float $v, string $k): array => [
                    'key' => $k,
                    'label' => $k,
                    'value' => round($v, 2),
                ])
                ->sortByDesc('value')
                ->values()
                ->take(10)
                ->all(),
            'top_items' => collect($byItem)
                ->map(fn (array $row): array => [
                    'item' => $row['item'],
                    'qty' => round($row['qty'], 2),
                    'towers' => count($row['towers']),
                    'value' => round($row['value'], 2),
                ])
                ->sortByDesc('value')
                ->values()
                ->take(20)
                ->all(),
            'by_warehouse' => collect($byWarehouse)
                ->map(fn (array $row): array => [
                    'warehouse' => $row['warehouse'],
                    'slips' => $row['slips'],
                    'towers' => count($row['towers']),
                    'value' => round($row['value'], 2),
                ])
                ->sortByDesc('value')
                ->values()
                ->all(),
            'filter_options' => [
                'regions' => collect($sites)->map(fn (array $s): string => (string) ($s['values']['region'] ?? ''))->filter()->unique()->sort()->values()->all(),
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
