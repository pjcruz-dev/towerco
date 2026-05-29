<?php

declare(strict_types=1);

namespace App\Modules\FiberOne\Services;

use App\Modules\FiberOne\Models\FiberRoute;

final class FiberOneDashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $total = FiberRoute::query()->count();
        $active = FiberRoute::query()->where('status', 'active')->count();
        $planned = FiberRoute::query()->where('status', 'planned')->count();

        $routes = FiberRoute::query()
            ->with(['fromSite:id,name', 'toSite:id,name'])
            ->orderBy('name')
            ->limit(25)
            ->get()
            ->map(fn (FiberRoute $r) => [
                'id' => (string) $r->id,
                'name' => $r->name,
                'status' => $r->status,
                'from' => $r->fromSite?->name,
                'to' => $r->toSite?->name,
                'length_km' => $r->length_km !== null ? (string) $r->length_km : null,
            ])
            ->values()
            ->all();

        return [
            'kpis' => [
                ['key' => 'fiber_routes', 'label' => 'Fiber routes', 'value' => (string) $total, 'change' => 'Logical spans', 'tone' => 'success'],
                ['key' => 'fiber_active', 'label' => 'Active', 'value' => (string) $active, 'change' => 'In service', 'tone' => 'success'],
                ['key' => 'fiber_planned', 'label' => 'Planned', 'value' => (string) $planned, 'change' => 'Engineering queue', 'tone' => $planned > 0 ? 'warning' : 'success'],
                ['key' => 'fiber_cross', 'label' => 'Cross-connects', 'value' => '—', 'change' => 'Extend schema as needed', 'tone' => 'success'],
            ],
            'routes' => $routes,
        ];
    }
}
