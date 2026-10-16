<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DocumentSeriesPack;
use App\Modules\DynamicEntities\Support\DynModulePack;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Facades\DB;

final class DocumentSeriesSeedService
{
    public function __construct(
        private readonly DynRecordService $records,
    ) {}

    /**
     * @return array{entities: int, fields: int, rows: int}
     */
    public function seed(TenantUser $actor): array
    {
        $stats = ['entities' => 0, 'fields' => 0, 'rows' => 0];

        DB::transaction(function () use ($actor, &$stats): void {
            $subsidiaries = $this->ensureEntity(
                DocumentSeriesPack::SUBSIDIARIES,
                'Subsidiaries',
                'Companies a document series belongs to.',
                40,
                $actor,
                $stats,
            );
            $this->ensureFields($subsidiaries, DocumentSeriesPack::subsidiaryFields(), [], $stats);

            $series = $this->ensureEntity(
                DocumentSeriesPack::SERIES,
                'Document Series',
                'Prefix, document type, and number format for each subsidiary.',
                50,
                $actor,
                $stats,
            );
            $this->ensureFields($series, DocumentSeriesPack::seriesFields(), [
                DocumentSeriesPack::SUBSIDIARIES => (string) $subsidiaries->id,
            ], $stats);

        });

        $subsidiaries = DynEntity::query()->where('slug', DocumentSeriesPack::SUBSIDIARIES)->firstOrFail();
        $series = DynEntity::query()->where('slug', DocumentSeriesPack::SERIES)->firstOrFail();
        $subsidiaryRecords = $this->ensureSubsidiaries($subsidiaries, $actor, $stats);
        $stats['rows'] += $this->ensureSeries($series, $subsidiaryRecords, $actor);

        return $stats;
    }

    /**
     * @param  array{entities: int, fields: int, rows: int}  $stats
     */
    private function ensureEntity(
        string $slug,
        string $name,
        string $description,
        int $sortOrder,
        TenantUser $actor,
        array &$stats,
    ): DynEntity {
        $entity = DynEntity::query()->where('slug', $slug)->first();
        if ($entity instanceof DynEntity) {
            return $entity;
        }

        $stats['entities']++;

        return DynEntity::query()->create([
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'module_pack' => DynModulePack::REFERENCE,
            'storage_mode' => 'json',
            'is_location_based' => false,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $defs
     * @param  array<string, string>  $targets
     * @param  array{entities: int, fields: int, rows: int}  $stats
     */
    private function ensureFields(DynEntity $entity, array $defs, array $targets, array &$stats): void
    {
        foreach ($defs as $def) {
            $exists = DynField::query()
                ->where('entity_id', $entity->id)
                ->where('name', $def['name'])
                ->exists();
            if ($exists) {
                continue;
            }

            $targetSlug = $def['target_slug'] ?? null;
            DynField::query()->create([
                'entity_id' => $entity->id,
                'name' => $def['name'],
                'label' => $def['label'],
                'type' => $def['type'],
                'is_required' => (bool) ($def['is_required'] ?? false),
                'is_key' => (bool) ($def['is_key'] ?? false),
                'is_system_field' => (bool) ($def['is_system_field'] ?? false),
                'system_column' => ($def['is_system_field'] ?? false) ? $def['name'] : null,
                'show_in_table' => (bool) ($def['show_in_table'] ?? false),
                'is_filterable' => (bool) ($def['is_filterable'] ?? false),
                'options_json' => $def['options_json'] ?? null,
                'target_entity_id' => is_string($targetSlug) ? ($targets[$targetSlug] ?? null) : null,
                'column_span' => 6,
                'field_order' => (int) $def['field_order'],
            ]);
            $stats['fields']++;
        }
    }

    /**
     * @param  array{entities: int, fields: int, rows: int}  $stats
     * @return array<string, string> code => record id
     */
    private function ensureSubsidiaries(DynEntity $entity, TenantUser $actor, array &$stats): array
    {
        $wanted = [
            'ADC' => 'ADC – Alliance Digital Infrastructure',
            'ATC' => 'ATC – Alliance Towers Corporation',
        ];
        $ids = [];
        foreach ($wanted as $code => $title) {
            $existing = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->where('source_external_id', $code)
                ->first();
            if ($existing instanceof DynRecord) {
                $ids[$code] = (string) $existing->id;

                continue;
            }

            $stats['rows']++;
            $record = $this->records->create($entity, [
                'title' => $title,
                'source_external_id' => $code,
                'status' => 'Active',
                'values' => [
                    'code' => $code,
                    'name' => $title,
                    'status' => 'Active',
                ],
            ], $actor, false);
            $ids[$code] = (string) $record->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, string>  $subsidiaryIds
     */
    private function ensureSeries(DynEntity $entity, array $subsidiaryIds, TenantUser $actor): int
    {
        $created = 0;
        foreach (DocumentSeriesPack::seriesRows() as $row) {
            $exists = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->where('source_external_id', $row['external_id'])
                ->exists();
            if ($exists) {
                continue;
            }

            $this->records->create($entity, [
                'title' => $row['prefix'].' — '.$row['document_type'],
                'source_external_id' => $row['external_id'],
                'status' => 'Active',
                'values' => [
                    'status' => 'Active',
                    'subsidiary_id' => $subsidiaryIds[$row['subsidiary']] ?? null,
                    'document_type' => $row['document_type'],
                    'series_prefix' => $row['prefix'],
                    'number_format' => $row['number_format'],
                ],
            ], $actor, false);
            $created++;
        }

        return $created;
    }
}
