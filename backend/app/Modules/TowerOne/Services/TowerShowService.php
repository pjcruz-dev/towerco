<?php

declare(strict_types=1);

namespace App\Modules\TowerOne\Services;

use App\Modules\TowerOne\Models\Tower;

final class TowerShowService
{
    /**
     * @return array<string, mixed>
     */
    public function asDetail(Tower $tower): array
    {
        $tower->load(['site:id,site_code,name']);

        return [
            'id' => $tower->id,
            'tower_type' => $tower->tower_type,
            'height_m' => $tower->height_m,
            'capacity_kg' => $tower->capacity_kg,
            'max_tenants' => $tower->max_tenants,
            'status' => $tower->status,
            'site' => $tower->site ? [
                'id' => $tower->site->id,
                'site_code' => $tower->site->site_code,
                'name' => $tower->site->name,
            ] : null,
            'created_at' => $tower->created_at?->toIso8601String(),
            'updated_at' => $tower->updated_at?->toIso8601String(),
        ];
    }
}
