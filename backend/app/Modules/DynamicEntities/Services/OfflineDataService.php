<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\AssetManagement\Models\Asset;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\AssetPrintCatalog;
use App\Modules\Workspace\Services\TenantActivityLogger;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class OfflineDataService
{
    public const ROW_CAP = 5000;

    public function __construct(
        private readonly TenantActivityLogger $activity,
    ) {}

    public static function formatMegabytes(int $bytes): string
    {
        if ($bytes < 0) {
            $bytes = 0;
        }

        return number_format($bytes / 1_048_576, 1).'MB';
    }

    /**
     * @return list<array{slug: string, name: string, rows: int, bytes: int, size: string}>
     */
    public function catalog(bool $canEntities, bool $canAssets): array
    {
        $entities = $this->visibleEntities($canEntities, $canAssets);
        $dynStats = $this->dynStats();
        $assetStats = $canAssets ? $this->assetStats() : ['rows' => 0, 'bytes' => 0];
        $rows = [];

        foreach ($entities as $entity) {
            $stats = $entity->slug === AssetPrintCatalog::SLUG
                ? $assetStats
                : ($dynStats[$entity->id] ?? ['rows' => 0, 'bytes' => 0]);
            $bytes = (int) $stats['bytes'];
            $rows[] = [
                'slug' => $entity->slug,
                'name' => $entity->name,
                'rows' => (int) $stats['rows'],
                'bytes' => $bytes,
                'size' => self::formatMegabytes($bytes),
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

        return $rows;
    }

    /**
     * @param  list<string>  $slugs
     * @return array{exported_at: string, entities: list<array<string, mixed>>}
     */
    public function download(array $slugs, bool $canEntities, bool $canAssets, ?Authenticatable $actor): array
    {
        $wanted = [];
        foreach ($slugs as $slug) {
            if (is_string($slug) && $slug !== '') {
                $wanted[$slug] = true;
            }
        }

        $entities = [];
        foreach ($this->catalog($canEntities, $canAssets) as $row) {
            if (isset($wanted[$row['slug']])) {
                $entities[] = $this->exportEntity($row['slug'], $row['name']);
            }
        }

        try {
            $this->activity->record(
                module: 'dynamic_entities',
                action: 'offline_data.downloaded',
                summary: 'Offline data downloaded · '.count($entities).' entities',
                entityType: 'offline_data',
                entityId: null,
                entityLabel: 'Selective cloud sync',
                actor: $actor,
                metadata: ['slugs' => array_column($entities, 'slug')],
            );
        } catch (\Throwable $e) {
            Log::warning('dynamic_entities.offline_download_audit_failed', ['error' => $e->getMessage()]);
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'entities' => $entities,
        ];
    }

    /**
     * @return list<DynEntity>
     */
    private function visibleEntities(bool $canEntities, bool $canAssets): array
    {
        $query = DynEntity::query()->where('is_active', true)->orderBy('name');
        if (! $canEntities && $canAssets) {
            $query->where('slug', AssetPrintCatalog::SLUG);
        } elseif ($canEntities && ! $canAssets) {
            $query->where('slug', '!=', AssetPrintCatalog::SLUG);
        } elseif (! $canEntities && ! $canAssets) {
            return [];
        }

        return $query->get()->all();
    }

    /**
     * @return array<string, array{rows: int, bytes: int}>
     */
    private function dynStats(): array
    {
        try {
            $stats = DB::connection('tenant')->table('dyn_records')
                ->where('is_deleted', false)
                ->groupBy('entity_id')
                ->selectRaw('entity_id, COUNT(*) as row_count, COALESCE(SUM(CHAR_LENGTH(values_json)), 0) as json_bytes')
                ->get();
        } catch (\Throwable) {
            return [];
        }

        $out = [];
        foreach ($stats as $row) {
            $count = (int) $row->row_count;
            $out[(string) $row->entity_id] = [
                'rows' => $count,
                'bytes' => (int) $row->json_bytes + ($count * 256),
            ];
        }

        return $out;
    }

    /**
     * @return array{rows: int, bytes: int}
     */
    private function assetStats(): array
    {
        try {
            $row = DB::connection('tenant')->table('it_assets')
                ->selectRaw('COUNT(*) as row_count, COALESCE(SUM(CHAR_LENGTH(custom_values_json)), 0) as json_bytes')
                ->first();
        } catch (\Throwable) {
            $count = Asset::query()->count();

            return ['rows' => $count, 'bytes' => $count * 512];
        }

        $count = (int) ($row->row_count ?? 0);

        return [
            'rows' => $count,
            'bytes' => (int) ($row->json_bytes ?? 0) + ($count * 256),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function exportEntity(string $slug, string $name): array
    {
        if ($slug === AssetPrintCatalog::SLUG) {
            $total = Asset::query()->count();
            $assets = Asset::query()->orderByDesc('updated_at')->limit(self::ROW_CAP)->get();
            $rows = [];
            foreach ($assets as $asset) {
                $rows[] = [
                    'id' => $asset->id,
                    'asset_tag' => $asset->asset_tag,
                    'serial_number' => $asset->serial_number,
                    'asset_type' => $asset->asset_type,
                    'status' => $asset->status,
                    'manufacturer' => $asset->manufacturer,
                    'product_name' => $asset->product_name,
                    'hostname' => $asset->hostname,
                    'assigned_user_id' => $asset->assigned_user_id,
                    'custom_values' => $asset->custom_values_json,
                    'notes' => $asset->notes,
                    'updated_at' => $asset->updated_at?->toIso8601String(),
                ];
            }

            return [
                'slug' => $slug,
                'name' => $name,
                'row_count' => $total,
                'truncated' => $total > count($rows),
                'rows' => $rows,
            ];
        }

        $entity = DynEntity::query()->where('slug', $slug)->first();
        if (! $entity instanceof DynEntity) {
            return ['slug' => $slug, 'name' => $name, 'row_count' => 0, 'truncated' => false, 'rows' => []];
        }

        $total = DynRecord::query()->where('entity_id', $entity->id)->where('is_deleted', false)->count();
        $records = DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false)
            ->orderByDesc('updated_at')
            ->limit(self::ROW_CAP)
            ->get();
        $rows = [];
        foreach ($records as $record) {
            $rows[] = [
                'id' => $record->id,
                'title' => $record->title,
                'status' => $record->status,
                'values' => $record->values_json,
                'updated_at' => $record->updated_at?->toIso8601String(),
            ];
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'row_count' => $total,
            'truncated' => $total > count($rows),
            'rows' => $rows,
        ];
    }
}
