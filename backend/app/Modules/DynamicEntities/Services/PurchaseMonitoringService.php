<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Aggregates purchase_transactions (+ items) dyn_records for ATC Purchase Monitoring.
 */
final class PurchaseMonitoringService
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null, supplier_id?: string|null, transaction_type?: string|null}  $filters
     * @return array<string, mixed>
     */
    public function build(array $filters = []): array
    {
        $entity = DynEntity::query()->where('slug', 'purchase_transactions')->first();
        if ($entity === null) {
            return $this->emptyPayload('Purchase Transactions entity is not imported yet.');
        }

        $query = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false);

        $dateFrom = $this->parseDate($filters['date_from'] ?? null);
        $dateTo = $this->parseDate($filters['date_to'] ?? null);
        $supplierFilter = isset($filters['supplier_id']) && $filters['supplier_id'] !== ''
            ? (string) $filters['supplier_id']
            : null;
        $typeFilter = isset($filters['transaction_type']) && $filters['transaction_type'] !== ''
            ? (string) $filters['transaction_type']
            : null;

        /** @var Collection<int, DynRecord> $records */
        $records = $query->get()->filter(function (DynRecord $record) use ($dateFrom, $dateTo, $supplierFilter, $typeFilter): bool {
            $values = $record->values_json ?? [];
            $date = $this->parseDate($values['purchase_date'] ?? $record->created_at?->toDateString());
            if ($dateFrom !== null && ($date === null || $date->lt($dateFrom))) {
                return false;
            }
            if ($dateTo !== null && ($date === null || $date->gt($dateTo))) {
                return false;
            }
            if ($supplierFilter !== null) {
                $sid = (string) ($values['supplier_id'] ?? '');
                if ($sid !== $supplierFilter) {
                    return false;
                }
            }
            if ($typeFilter !== null && $typeFilter !== 'all') {
                $type = (string) ($values['transaction_type'] ?? '');
                if (! $this->typeMatches($type, $typeFilter)) {
                    return false;
                }
            }

            return true;
        })->values();

        $supplierNames = $this->supplierNameMap();
        $totalPurchases = 0.0;
        $poTotal = 0.0;
        $poCount = 0;
        $grTotal = 0.0;
        $grCount = 0;
        $supplierTotals = [];
        $monthly = [];
        $supplierIds = [];

        foreach ($records as $record) {
            $values = $record->values_json ?? [];
            $amount = $this->money($values['total_amount'] ?? 0);
            $type = (string) ($values['transaction_type'] ?? '');
            $supplierId = (string) ($values['supplier_id'] ?? '');
            $date = $this->parseDate($values['purchase_date'] ?? null);
            $monthKey = $date?->format('Y-m') ?? 'unknown';

            $totalPurchases += $amount;
            if ($supplierId !== '') {
                $supplierIds[$supplierId] = true;
                $supplierTotals[$supplierId] = ($supplierTotals[$supplierId] ?? 0) + $amount;
            }

            if (! isset($monthly[$monthKey])) {
                $monthly[$monthKey] = ['month' => $monthKey, 'purchase_orders' => 0.0, 'goods_receipts' => 0.0];
            }

            if ($this->isPurchaseOrder($type, (string) ($values['purchase_number'] ?? ''))) {
                $poTotal += $amount;
                $poCount++;
                $monthly[$monthKey]['purchase_orders'] += $amount;
            }
            if ($this->isGoodsReceipt($type)) {
                $grTotal += $amount;
                $grCount++;
                $monthly[$monthKey]['goods_receipts'] += $amount;
            }
        }

        ksort($monthly);

        $bySupplier = collect($supplierTotals)
            ->map(fn (float $total, string $id): array => [
                'supplier_id' => $id,
                'supplier_name' => $supplierNames[$id] ?? $this->shortId($id),
                'total' => round($total, 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->take(12)
            ->all();

        $recent = $records
            ->sortByDesc(fn (DynRecord $r): string => (string) (($r->values_json['purchase_date'] ?? '') ?: $r->created_at?->toDateString() ?? ''))
            ->take(50)
            ->map(function (DynRecord $record) use ($supplierNames): array {
                $values = $record->values_json ?? [];
                $supplierId = (string) ($values['supplier_id'] ?? '');

                return [
                    'id' => $record->id,
                    'ref' => (string) ($values['purchase_number'] ?? $record->title ?? $record->source_external_id ?? ''),
                    'date' => (string) ($values['purchase_date'] ?? ''),
                    'supplier' => $supplierNames[$supplierId] ?? ($supplierId !== '' ? $this->shortId($supplierId) : '—'),
                    'type' => (string) ($values['transaction_type'] ?? ''),
                    'status' => (string) ($record->status ?? $values['status'] ?? ''),
                    'total_amount' => $this->money($values['total_amount'] ?? 0),
                ];
            })
            ->values()
            ->all();

        $topItems = $this->topPurchasedItems();

        $types = $records
            ->map(fn (DynRecord $r): string => (string) (($r->values_json['transaction_type'] ?? '') ?: 'Unknown'))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $supplierOptions = collect($supplierNames)
            ->map(fn (string $name, string $id): array => ['id' => $id, 'name' => $name])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'message' => null,
            'filters' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
                'supplier_id' => $supplierFilter,
                'transaction_type' => $typeFilter,
            ],
            'kpis' => [
                'total_purchases' => round($totalPurchases, 2),
                'total_transactions' => $records->count(),
                'purchase_orders_total' => round($poTotal, 2),
                'purchase_orders_count' => $poCount,
                'goods_received_total' => round($grTotal, 2),
                'goods_received_count' => $grCount,
                'active_suppliers' => count($supplierIds),
            ],
            'monthly_trend' => array_values($monthly),
            'by_supplier' => $bySupplier,
            'recent_transactions' => $recent,
            'top_items' => $topItems,
            'filter_options' => [
                'transaction_types' => $types,
                'suppliers' => $supplierOptions,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyPayload(string $message): array
    {
        return [
            'message' => $message,
            'filters' => [],
            'kpis' => [
                'total_purchases' => 0,
                'total_transactions' => 0,
                'purchase_orders_total' => 0,
                'purchase_orders_count' => 0,
                'goods_received_total' => 0,
                'goods_received_count' => 0,
                'active_suppliers' => 0,
            ],
            'monthly_trend' => [],
            'by_supplier' => [],
            'recent_transactions' => [],
            'top_items' => [],
            'filter_options' => [
                'transaction_types' => [],
                'suppliers' => [],
            ],
        ];
    }

    /**
     * @return array<string, string> uuid => display name
     */
    private function supplierNameMap(): array
    {
        $map = [];
        foreach (['suppliers', 'vendors'] as $slug) {
            $entity = DynEntity::query()->where('slug', $slug)->first();
            if ($entity === null) {
                continue;
            }
            DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->get(['id', 'title', 'values_json'])
                ->each(function (DynRecord $r) use (&$map): void {
                    $values = $r->values_json ?? [];
                    $name = $r->title
                        ?: (string) ($values['supplier_name'] ?? $values['vendor_name'] ?? $values['name'] ?? '');
                    $map[$r->id] = $name !== '' ? $name : $this->shortId($r->id);
                });
        }

        return $map;
    }

    /**
     * @return list<array{description: string, supplier: string, qty: float, total_cost: float}>
     */
    private function topPurchasedItems(): array
    {
        $entity = DynEntity::query()->where('slug', 'purchase_transaction_items')->first();
        if ($entity === null) {
            return [];
        }

        $productNames = [];
        $products = DynEntity::query()->where('slug', 'products')->first();
        if ($products !== null) {
            DynRecord::query()
                ->where('entity_id', $products->id)
                ->where('is_deleted', false)
                ->get(['id', 'title', 'values_json'])
                ->each(function (DynRecord $r) use (&$productNames): void {
                    $values = $r->values_json ?? [];
                    $productNames[$r->id] = $r->title
                        ?: (string) ($values['product_name'] ?? $values['name'] ?? $values['description'] ?? $this->shortId($r->id));
                });
        }

        $agg = [];
        DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false)
            ->get()
            ->each(function (DynRecord $r) use (&$agg, $productNames): void {
                $values = $r->values_json ?? [];
                $productId = (string) ($values['product_id'] ?? '');
                $desc = (string) ($values['description'] ?? '');
                if ($desc === '' && $productId !== '') {
                    $desc = $productNames[$productId] ?? '(no description)';
                }
                if ($desc === '') {
                    $desc = '(no description)';
                }
                $key = $desc.'|'.$productId;
                if (! isset($agg[$key])) {
                    $agg[$key] = [
                        'description' => $desc,
                        'supplier' => '—',
                        'qty' => 0.0,
                        'total_cost' => 0.0,
                    ];
                }
                $agg[$key]['qty'] += $this->money($values['quantity'] ?? 0);
                $agg[$key]['total_cost'] += $this->money($values['line_total'] ?? 0);
            });

        return collect($agg)
            ->sortByDesc('total_cost')
            ->values()
            ->take(20)
            ->map(fn (array $row): array => [
                'description' => $row['description'],
                'supplier' => $row['supplier'],
                'qty' => round($row['qty'], 2),
                'total_cost' => round($row['total_cost'], 2),
            ])
            ->all();
    }

    private function isGoodsReceipt(string $type): bool
    {
        $t = strtolower($type);

        return str_contains($t, 'goods') || $t === 'gr' || str_contains($t, 'receipt');
    }

    private function isPurchaseOrder(string $type, string $number): bool
    {
        $t = strtolower($type);
        if (str_contains($t, 'purchase order') || $t === 'po' || str_contains($t, 'order')) {
            return true;
        }
        // Seed data often labels GR rows with PO- numbers; count as PO only when type is explicit PO.
        return str_starts_with(strtoupper($number), 'PO-') && ! $this->isGoodsReceipt($type);
    }

    private function typeMatches(string $actual, string $filter): bool
    {
        if (strcasecmp($actual, $filter) === 0) {
            return true;
        }
        if ($filter === 'po') {
            return $this->isPurchaseOrder($actual, '');
        }
        if ($filter === 'gr') {
            return $this->isGoodsReceipt($actual);
        }

        return false;
    }

    private function money(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }

        return (float) preg_replace('/[^\d.\-]/', '', (string) $value);
    }

    private function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function shortId(string $id): string
    {
        return Str::isUuid($id) ? substr($id, 0, 8) : $id;
    }
}
