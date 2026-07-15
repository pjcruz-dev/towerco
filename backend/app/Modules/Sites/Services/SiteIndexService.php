<?php

declare(strict_types=1);

namespace App\Modules\Sites\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SiteIndexService
{
    private const SORTABLE = [
        'site_code',
        'name',
        'type',
        'status',
        'updated_at',
        'created_at',
    ];

    public function paginate(int $page, int $perPage, string $search, ?string $sort = null): LengthAwarePaginator
    {
        $query = Site::query();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('site_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('type', 'like', $like)
                    ->orWhere('status', 'like', $like);
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'site_code:asc'),
            self::SORTABLE,
            'site_code',
            'asc',
        );
        $query->orderBy($column, $direction);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, int>}
     */
    public function asPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'data' => $paginator->getCollection()->map(static function (Site $site): array {
                return [
                    'id' => $site->id,
                    'site_code' => $site->site_code,
                    'name' => $site->name,
                    'latitude' => $site->latitude,
                    'longitude' => $site->longitude,
                    'type' => $site->type,
                    'status' => $site->status,
                    'created_at' => $site->created_at?->toIso8601String(),
                    'updated_at' => $site->updated_at?->toIso8601String(),
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
