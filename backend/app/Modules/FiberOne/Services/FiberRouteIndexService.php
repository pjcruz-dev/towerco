<?php

declare(strict_types=1);

namespace App\Modules\FiberOne\Services;

use App\Modules\FiberOne\Models\FiberRoute;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class FiberRouteIndexService
{
    public function paginate(int $page, int $perPage, string $search): LengthAwarePaginator
    {
        $query = FiberRoute::query()
            ->with([
                'fromSite:id,site_code,name',
                'toSite:id,site_code,name',
            ])
            ->orderBy('name');

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('name', 'like', $like)
                    ->orWhere('status', 'like', $like);
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
            'data' => $paginator->getCollection()->map(static function (FiberRoute $route): array {
                return [
                    'id' => $route->id,
                    'name' => $route->name,
                    'status' => $route->status,
                    'length_km' => $route->length_km,
                    'from_site' => $route->fromSite ? [
                        'id' => $route->fromSite->id,
                        'site_code' => $route->fromSite->site_code,
                        'name' => $route->fromSite->name,
                    ] : null,
                    'to_site' => $route->toSite ? [
                        'id' => $route->toSite->id,
                        'site_code' => $route->toSite->site_code,
                        'name' => $route->toSite->name,
                    ] : null,
                    'created_at' => $route->created_at?->toIso8601String(),
                    'updated_at' => $route->updated_at?->toIso8601String(),
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
