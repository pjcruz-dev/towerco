<?php

declare(strict_types=1);

namespace App\Modules\TowerOne\Services;

use App\Modules\TowerOne\Models\Tower;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class TowerIndexService
{
    public function paginate(int $page, int $perPage, string $search): LengthAwarePaginator
    {
        $query = Tower::query()
            ->with(['site:id,site_code,name'])
            ->orderByDesc('updated_at');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('tower_type', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhereHas('site', static function ($site) use ($like): void {
                        $site->where('site_code', 'like', $like)
                            ->orWhere('name', 'like', $like);
                    });
            });
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()->map(static function (Tower $tower): array {
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
            })->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
