<?php

declare(strict_types=1);

namespace App\Modules\AssetOne\Services;

use App\Core\Support\AllowlistedSort;
use App\Modules\AssetOne\Models\Asset;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AssetIndexService
{
    private const SORTABLE = [
        'asset_code',
        'name',
        'category',
        'status',
        'warranty_expiry',
        'updated_at',
        'created_at',
    ];

    public function paginate(int $page, int $perPage, string $search, ?string $sort = null): LengthAwarePaginator
    {
        $query = Asset::query();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(static function ($q) use ($like): void {
                $q->where('asset_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('category', 'like', $like)
                    ->orWhere('status', 'like', $like)
                    ->orWhere('rfid_tag', 'like', $like);
            });
        }

        [$column, $direction] = AllowlistedSort::resolve(
            (string) ($sort ?? 'asset_code:asc'),
            self::SORTABLE,
            'asset_code',
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
            'data' => $paginator->getCollection()->map(static function (Asset $asset): array {
                return [
                    'id' => $asset->id,
                    'asset_code' => $asset->asset_code,
                    'name' => $asset->name,
                    'category' => $asset->category,
                    'status' => $asset->status,
                    'rfid_tag' => $asset->rfid_tag,
                    'location_type' => $asset->location_type,
                    'location_id' => $asset->location_id,
                    'warranty_expiry' => $asset->warranty_expiry?->toDateString(),
                    'purchase_value' => $asset->purchase_value,
                    'created_at' => $asset->created_at?->toIso8601String(),
                    'updated_at' => $asset->updated_at?->toIso8601String(),
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
