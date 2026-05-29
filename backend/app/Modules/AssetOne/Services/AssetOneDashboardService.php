<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Modules\AssetOne\Models\Asset;
use Illuminate\Support\Facades\DB;

final class AssetOneDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $total = Asset::query()->count();
        $inWarehouse = Asset::query()->where('status', 'in_warehouse')->count();
        $deployed = Asset::query()->where('status', 'deployed')->count();
        $inTransit = Asset::query()->where('status', 'in_transit')->count();

        $byCategory = Asset::query()
            ->select('category', DB::raw('count(*) as c'))
            ->groupBy('category')
            ->orderByDesc('c')
            ->limit(5)
            ->get()
            ->map(fn ($row) => ['category' => (string) $row->category, 'count' => (int) $row->c])
            ->values()
            ->all();

        $recent = Asset::query()
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(fn (Asset $a) => [
                'id' => (string) $a->id,
                'asset_code' => $a->asset_code,
                'name' => $a->name,
                'category' => $a->category,
                'status' => $a->status,
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['key' => 'assets_total', 'label' => 'Assets', 'value' => (string) $total, 'change' => 'Tracked inventory', 'tone' => 'success'],
                ['key' => 'assets_wh', 'label' => 'In warehouse', 'value' => (string) $inWarehouse, 'change' => 'Available stock', 'tone' => 'success'],
                ['key' => 'assets_dep', 'label' => 'Deployed', 'value' => (string) $deployed, 'change' => 'In the field', 'tone' => 'success'],
                ['key' => 'assets_transit', 'label' => 'In transit', 'value' => (string) $inTransit, 'change' => 'Logistics pipeline', 'tone' => $inTransit > 0 ? 'warning' : 'success'],
            ],
            'by_category' => $byCategory,
            'assets' => $recent,
        ];
    }
}
