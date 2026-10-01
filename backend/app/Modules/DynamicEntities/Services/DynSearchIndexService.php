<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynHtmlReport;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Models\DynRecordIndex;
use App\Modules\DynamicEntities\Models\DynWorkflow;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Admin surface for the Dynamic Entities search / filter index (Ctrl+K + list filters).
 */
final class DynSearchIndexService
{
    private const META_CACHE_KEY = 'toweros.search_index.meta';

    public function __construct(
        private readonly DynRecordService $records,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function status(): array
    {
        $coverage = $this->coverageByEntity();
        $drift = [];
        foreach ($coverage as $row) {
            if (($row['status'] ?? '') !== 'indexed') {
                $drift[] = (string) $row['slug'];
            }
        }

        $records = $this->safeCount(fn (): int => (int) DynRecord::query()->where('is_deleted', false)->count());
        $tables = $this->safeCount(fn (): int => (int) DynEntity::query()->count());
        $fields = $this->safeCount(fn (): int => (int) DynField::query()->count());
        $reports = $this->tableExists('dyn_html_reports')
            ? $this->safeCount(fn (): int => (int) DynHtmlReport::query()->count())
            : 0;
        $workflows = $this->tableExists('dyn_workflows')
            ? $this->safeCount(fn (): int => (int) DynWorkflow::query()->count())
            : 0;
        $people = $this->safeCount(fn (): int => (int) TenantUser::query()->count());
        $indexRows = $this->tableExists('dyn_record_indexes')
            ? $this->safeCount(fn (): int => (int) DynRecordIndex::query()->count())
            : 0;

        $meta = $this->meta();

        return [
            'total_indexed' => $indexRows,
            'summary' => [
                'records' => $records,
                'tables' => $tables,
                'fields' => $fields,
                'reports' => $reports,
                'workflows' => $workflows,
                'apps' => 0,
                'pages' => 0,
                'people' => $people,
            ],
            'drift' => [
                'detected' => $drift !== [],
                'slugs' => array_values(array_slice($drift, 0, 40)),
                'message' => $drift === []
                    ? null
                    : 'Drift detected in: '.implode(', ', array_slice($drift, 0, 12))
                        .(count($drift) > 12 ? '…' : '')
                        .'. This repairs itself on the next search; use Repair now to do it immediately.',
            ],
            'last_indexed_at' => $meta['last_indexed_at'] ?? null,
            'last_action' => $meta['last_action'] ?? null,
            'coverage' => $coverage,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function repair(): array
    {
        $rebuilt = 0;
        $entities = 0;
        foreach ($this->coverageByEntity() as $row) {
            if (($row['status'] ?? '') === 'indexed') {
                continue;
            }
            $entity = DynEntity::query()->where('slug', $row['slug'])->first();
            if (! $entity) {
                continue;
            }
            $rebuilt += $this->rebuildEntity($entity, 2000);
            $entities++;
        }
        $this->touchMeta('repair');

        return [
            'action' => 'repair',
            'entities_rebuilt' => $entities,
            'records_rebuilt' => $rebuilt,
            'status' => $this->status(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function fullRebuild(int $maxPerEntity = 5000): array
    {
        $rebuilt = 0;
        $entities = 0;
        foreach (DynEntity::query()->orderBy('name')->get() as $entity) {
            $rebuilt += $this->rebuildEntity($entity, $maxPerEntity);
            $entities++;
        }
        $this->touchMeta('full_rebuild');

        return [
            'action' => 'full_rebuild',
            'entities_rebuilt' => $entities,
            'records_rebuilt' => $rebuilt,
            'status' => $this->status(),
        ];
    }

    /**
     * Refresh bookkeeping without rewriting every value row.
     *
     * @return array<string, mixed>
     */
    public function rebuildMetadataOnly(): array
    {
        $this->touchMeta('rebuild_metadata');

        return [
            'action' => 'rebuild_metadata',
            'status' => $this->status(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rebuildEntityBySlug(string $slug, int $max = 5000): array
    {
        $entity = DynEntity::query()->where('slug', $slug)->orWhere('id', $slug)->firstOrFail();
        $rebuilt = $this->rebuildEntity($entity, $max);
        $this->touchMeta('rebuild_entity:'.$entity->slug);

        return [
            'action' => 'rebuild_entity',
            'entity_slug' => (string) $entity->slug,
            'records_rebuilt' => $rebuilt,
            'status' => $this->status(),
        ];
    }

    private function rebuildEntity(DynEntity $entity, int $max): int
    {
        if (! $this->tableExists('dyn_record_indexes')) {
            return 0;
        }

        $entity->loadMissing('fields');
        $count = 0;
        DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('is_deleted', false)
            ->orderBy('id')
            ->limit(max(1, min(20000, $max)))
            ->chunkById(100, function ($records) use ($entity, &$count): void {
                foreach ($records as $record) {
                    $this->records->rebuildIndexes($entity, $record);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function coverageByEntity(): array
    {
        if (! Schema::connection('tenant')->hasTable('dyn_entities')) {
            return [];
        }

        $entities = DynEntity::query()->orderBy('name')->get(['id', 'slug', 'name']);
        $recordCounts = DynRecord::query()
            ->where('is_deleted', false)
            ->selectRaw('entity_id, COUNT(*) as c')
            ->groupBy('entity_id')
            ->pluck('c', 'entity_id');

        $indexedCounts = collect();
        if ($this->tableExists('dyn_record_indexes')) {
            $indexedCounts = DynRecordIndex::query()
                ->selectRaw('entity_id, COUNT(DISTINCT record_id) as c')
                ->groupBy('entity_id')
                ->pluck('c', 'entity_id');
        }

        $out = [];
        foreach ($entities as $entity) {
            $records = (int) ($recordCounts[$entity->id] ?? 0);
            $indexed = (int) ($indexedCounts[$entity->id] ?? 0);
            $status = 'indexed';
            if ($records === 0) {
                $status = 'empty';
            } elseif ($indexed === 0) {
                $status = 'not_indexed';
            } elseif ($indexed < $records) {
                $status = 'partial';
            }

            $out[] = [
                'id' => (string) $entity->id,
                'slug' => (string) $entity->slug,
                'name' => (string) $entity->name,
                'record_count' => $records,
                'indexed_rows' => $indexed,
                'status' => $status,
            ];
        }

        return $out;
    }

    /**
     * @return array{last_indexed_at?: string|null, last_action?: string|null}
     */
    private function meta(): array
    {
        $raw = Cache::get(self::META_CACHE_KEY);
        if (! is_array($raw)) {
            return [];
        }

        return [
            'last_indexed_at' => isset($raw['last_indexed_at']) ? (string) $raw['last_indexed_at'] : null,
            'last_action' => isset($raw['last_action']) ? (string) $raw['last_action'] : null,
        ];
    }

    private function touchMeta(string $action): void
    {
        Cache::forever(self::META_CACHE_KEY, [
            'last_indexed_at' => now()->toDateTimeString(),
            'last_action' => $action,
        ]);
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::connection('tenant')->hasTable($table);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  callable(): int  $fn
     */
    private function safeCount(callable $fn): int
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
