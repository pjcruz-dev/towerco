<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\AtcImportIdMap;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\AtcFinancePack;
use App\Modules\DynamicEntities\Support\AtcPmRelatedTabs;
use App\Modules\DynamicEntities\Support\AtcProcurementPack;
use App\Modules\DynamicEntities\Support\AtcTicketingPack;
use App\Modules\DynamicEntities\Support\SqlDumpReader;
use App\Modules\Platform\Support\StructuredAuditLogWriter;

/**
 * Staging / cutover reconcile: dump eligible row counts vs dyn_records (ETL-sourced).
 */
final class AtcImportVerifyService
{
    public function __construct(
        private readonly SqlDumpReader $reader,
        private readonly StructuredAuditLogWriter $auditWriter,
    ) {}

    /**
     * @param  list<string>|null  $slugs
     * @return array{
     *   ok: bool,
     *   pack: string,
     *   dump_path: string,
     *   dump_bytes: int,
     *   tolerance: int,
     *   rows: list<array<string, mixed>>,
     *   summary: array{entities: int, matched: int, mismatched: int, missing_entity: int, missing_table: int, dump_eligible: int, dyn_imported: int}
     * }
     */
    public function verify(string $dumpPath, ?array $slugs = null, string $pack = 'all', int $tolerance = 0, ?string $tenantId = null): array
    {
        $sql = $this->reader->readFile($dumpPath);
        $targets = $slugs ?? $this->slugsForPack($pack);
        $dumpBytes = is_file($dumpPath) ? (int) filesize($dumpPath) : 0;

        $rows = [];
        $summary = [
            'entities' => 0,
            'matched' => 0,
            'mismatched' => 0,
            'missing_entity' => 0,
            'missing_table' => 0,
            'dump_eligible' => 0,
            'dyn_imported' => 0,
        ];

        foreach ($targets as $slug) {
            $entity = DynEntity::query()->where('slug', $slug)->first();
            if ($entity === null) {
                $rows[] = [
                    'slug' => $slug,
                    'table' => 'dat_'.$slug,
                    'entity_exists' => false,
                    'dump_rows' => 0,
                    'dump_eligible' => 0,
                    'dyn_imported' => 0,
                    'dyn_total' => 0,
                    'id_maps' => 0,
                    'delta' => null,
                    'status' => 'missing_entity',
                    'note' => 'Run atc:import-meta (and seed ticketing if Phase 5).',
                ];
                $summary['missing_entity']++;
                $summary['entities']++;
                continue;
            }

            $table = $entity->source_linked_table ?: ('dat_'.$slug);
            $dumpRows = $this->reader->extractInsertRows($sql, $table);
            if ($dumpRows === [] && $table !== 'dat_'.$slug) {
                $dumpRows = $this->reader->extractInsertRows($sql, 'dat_'.$slug);
                $table = 'dat_'.$slug;
            }

            if ($dumpRows === []) {
                // Greenfield packs (ticketing) have no dump tables — treat as skipped OK when dyn exists.
                $dynTotal = DynRecord::query()
                    ->where('entity_id', $entity->id)
                    ->where('is_deleted', false)
                    ->count();
                $status = in_array($slug, AtcTicketingPack::phase5EntitySlugs(), true)
                    ? 'skipped_no_dump'
                    : 'missing_table';
                $rows[] = [
                    'slug' => $slug,
                    'table' => $table,
                    'entity_exists' => true,
                    'dump_rows' => 0,
                    'dump_eligible' => 0,
                    'dyn_imported' => DynRecord::query()
                        ->where('entity_id', $entity->id)
                        ->where('is_deleted', false)
                        ->whereNotNull('source_external_id')
                        ->count(),
                    'dyn_total' => $dynTotal,
                    'id_maps' => AtcImportIdMap::query()
                        ->where('source_table', $table)
                        ->where('target_type', 'record')
                        ->count(),
                    'delta' => null,
                    'status' => $status,
                    'note' => $status === 'skipped_no_dump'
                        ? 'No Metacoresoft table in dump (greenfield / seed).'
                        : 'Table absent or empty in dump.',
                ];
                $summary['entities']++;
                if ($status === 'missing_table') {
                    $summary['missing_table']++;
                } else {
                    $summary['matched']++;
                }
                continue;
            }

            $eligible = 0;
            foreach ($dumpRows as $row) {
                $sourceId = isset($row['id']) ? (string) $row['id'] : '';
                if ($sourceId === '') {
                    continue;
                }
                if (isset($row['is_deleted']) && (int) $row['is_deleted'] === 1) {
                    continue;
                }
                $eligible++;
            }

            $dynImported = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->whereNotNull('source_external_id')
                ->count();
            $dynTotal = DynRecord::query()
                ->where('entity_id', $entity->id)
                ->where('is_deleted', false)
                ->count();
            $idMaps = AtcImportIdMap::query()
                ->where('source_table', $table)
                ->where('target_type', 'record')
                ->count();

            $delta = $eligible - $dynImported;
            $matched = abs($delta) <= $tolerance;
            $status = $matched ? 'ok' : 'mismatch';

            $rows[] = [
                'slug' => $slug,
                'table' => $table,
                'entity_exists' => true,
                'dump_rows' => count($dumpRows),
                'dump_eligible' => $eligible,
                'dyn_imported' => $dynImported,
                'dyn_total' => $dynTotal,
                'id_maps' => $idMaps,
                'delta' => $delta,
                'status' => $status,
                'note' => $matched
                    ? null
                    : sprintf('Eligible dump rows (%d) vs ETL records (%d); delta=%+d', $eligible, $dynImported, $delta),
            ];

            $summary['entities']++;
            $summary['dump_eligible'] += $eligible;
            $summary['dyn_imported'] += $dynImported;
            if ($matched) {
                $summary['matched']++;
            } else {
                $summary['mismatched']++;
            }
        }

        $ok = $summary['mismatched'] === 0 && $summary['missing_entity'] === 0;

        $result = [
            'ok' => $ok,
            'pack' => $pack,
            'dump_path' => $dumpPath,
            'dump_bytes' => $dumpBytes,
            'tolerance' => $tolerance,
            'rows' => $rows,
            'summary' => $summary,
        ];

        $this->auditWriter->write('atc_etl', 'atc.verify.completed', [
            'tenant_id' => $tenantId,
            'pack' => $pack,
            'dump_path' => basename($dumpPath),
            'dump_bytes' => $dumpBytes,
            'ok' => $ok,
            'summary' => $summary,
        ]);

        return $result;
    }

    /**
     * Count dump rows that would import (no writes).
     *
     * @param  list<string>|null  $slugs
     * @return array{entities: int, would_import: int, would_skip: int, missing: list<string>, rows: list<array<string, mixed>>}
     */
    public function previewImport(string $dumpPath, ?array $slugs = null, string $pack = 'phase1'): array
    {
        $sql = $this->reader->readFile($dumpPath);
        $targets = $slugs ?? $this->slugsForPack($pack);

        $stats = [
            'entities' => 0,
            'would_import' => 0,
            'would_skip' => 0,
            'missing' => [],
            'rows' => [],
        ];

        foreach ($targets as $slug) {
            $entity = DynEntity::query()->where('slug', $slug)->first();
            if ($entity === null) {
                $stats['missing'][] = $slug;
                $stats['rows'][] = [
                    'slug' => $slug,
                    'would_import' => 0,
                    'would_skip' => 0,
                    'status' => 'missing_entity',
                ];
                continue;
            }

            $table = $entity->source_linked_table ?: ('dat_'.$slug);
            $dumpRows = $this->reader->extractInsertRows($sql, $table);
            if ($dumpRows === [] && $table !== 'dat_'.$slug) {
                $dumpRows = $this->reader->extractInsertRows($sql, 'dat_'.$slug);
            }
            if ($dumpRows === []) {
                $stats['missing'][] = $slug.':'.$table;
                $stats['rows'][] = [
                    'slug' => $slug,
                    'would_import' => 0,
                    'would_skip' => 0,
                    'status' => 'missing_table',
                ];
                continue;
            }

            $wouldImport = 0;
            $wouldSkip = 0;
            foreach ($dumpRows as $row) {
                $sourceId = isset($row['id']) ? (string) $row['id'] : '';
                if ($sourceId === '' || (isset($row['is_deleted']) && (int) $row['is_deleted'] === 1)) {
                    $wouldSkip++;
                    continue;
                }
                $wouldImport++;
            }

            $stats['entities']++;
            $stats['would_import'] += $wouldImport;
            $stats['would_skip'] += $wouldSkip;
            $stats['rows'][] = [
                'slug' => $slug,
                'would_import' => $wouldImport,
                'would_skip' => $wouldSkip,
                'status' => 'ok',
            ];
        }

        return $stats;
    }

    /**
     * @return list<string>
     */
    public function slugsForPack(string $pack): array
    {
        return match (strtolower($pack)) {
            'phase2', 'procurement' => AtcProcurementPack::phase2EntitySlugs(),
            'phase3', 'finance' => AtcFinancePack::phase3EntitySlugs(),
            'phase5', 'ticketing' => AtcTicketingPack::phase5EntitySlugs(),
            'all' => AtcTicketingPack::allThroughPhase5(),
            default => AtcPmRelatedTabs::phase1EntitySlugs(),
        };
    }
}
