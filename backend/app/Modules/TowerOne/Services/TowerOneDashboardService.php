<?php

declare(strict_types=1);

namespace App\Modules\TowerOne\Services;

use App\Modules\TowerOne\Models\Tower;

final class TowerOneDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $total = Tower::query()->count();
        $operational = Tower::query()->where('status', 'operational')->count();
        $maintenance = Tower::query()->where('status', 'under_maintenance')->count();

        $recent = Tower::query()
            ->with('site:id,name,site_code')
            ->orderByDesc('updated_at')
            ->limit(15)
            ->get()
            ->map(fn (Tower $t) => [
                'id' => (string) $t->id,
                'site' => $t->site?->name ?? '—',
                'site_code' => $t->site?->site_code ?? '',
                'tower_type' => $t->tower_type,
                'status' => $t->status,
                'height_m' => $t->height_m !== null ? (string) $t->height_m : null,
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['key' => 'towers_total', 'label' => 'Towers', 'value' => (string) $total, 'change' => 'Registered structures', 'tone' => 'success'],
                ['key' => 'towers_ops', 'label' => 'Operational', 'value' => (string) $operational, 'change' => 'Status = operational', 'tone' => 'success'],
                ['key' => 'towers_maint', 'label' => 'Under maintenance', 'value' => (string) $maintenance, 'change' => 'Planned work', 'tone' => $maintenance > 0 ? 'warning' : 'success'],
                ['key' => 'colocation', 'label' => 'Co-location headroom', 'value' => $total > 0 ? 'Review sites' : '—', 'change' => 'Per-site max tenants', 'tone' => 'success'],
            ],
            'towers' => $recent,
        ];
    }
}
