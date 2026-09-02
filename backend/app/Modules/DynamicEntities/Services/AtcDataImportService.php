<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\AtcImportIdMap;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\AtcFinancePack;
use App\Modules\DynamicEntities\Support\AtcPmRelatedTabs;
use App\Modules\DynamicEntities\Support\AtcProcurementPack;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\DynamicEntities\Support\SqlDumpReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports Metacoresoft dat_* rows into dyn_records for Phase 1 PM / reference packs.
 */
final class AtcDataImportService
{
    /** @var list<string> */
    private const SKIP_COLUMNS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'is_deleted',
        'created_by',
        'updated_by',
        'assigned_user',
        'assigned_user_id',
    ];

    public function __construct(
        private readonly SqlDumpReader $reader,
        private readonly DynRecordService $records,
    ) {}

    /**
     * @param  list<string>|null  $slugs
     * @return array{entities: int, records: int, skipped: int, missing: list<string>}
     */
    public function importFromDump(string $dumpPath, ?array $slugs = null): array
    {
        $sql = $this->reader->readFile($dumpPath);
        $targets = $slugs ?? AtcPmRelatedTabs::phase1EntitySlugs();

        $stats = [
            'entities' => 0,
            'records' => 0,
            'skipped' => 0,
            'missing' => [],
        ];

        foreach ($targets as $slug) {
            $entity = DynEntity::query()->where('slug', $slug)->first();
            if ($entity === null) {
                $stats['missing'][] = $slug;
                continue;
            }

            $table = $entity->source_linked_table ?: ('dat_'.$slug);
            $rows = $this->reader->extractInsertRows($sql, $table);
            if ($rows === []) {
                // Some dumps use dat_{slug} without entity.linked_table alignment
                if ($table !== 'dat_'.$slug) {
                    $rows = $this->reader->extractInsertRows($sql, 'dat_'.$slug);
                }
            }

            if ($rows === []) {
                $stats['missing'][] = $slug.':'.$table;
                continue;
            }

            $imported = $this->importEntityRows($entity, $rows);
            $stats['entities']++;
            $stats['records'] += $imported['records'];
            $stats['skipped'] += $imported['skipped'];
        }

        $this->wireTowerSiteRelatedTabs();
        $this->wirePurchaseTransactionRelatedTabs();
        $this->wireSalesAndBankRelatedTabs();

        return $stats;
    }

    /**
     * @param  list<array<string, string|null>>  $rows
     * @return array{records: int, skipped: int}
     */
    private function importEntityRows(DynEntity $entity, array $rows): array
    {
        $entity->loadMissing('fields');
        $fieldsByColumn = $this->buildColumnFieldMap($entity);
        $parentFieldNames = $this->parentLinkFieldNames($entity->slug);

        $counts = ['records' => 0, 'skipped' => 0];

        DB::transaction(function () use ($entity, $rows, $fieldsByColumn, $parentFieldNames, &$counts): void {
            foreach ($rows as $row) {
                $sourceId = isset($row['id']) ? (string) $row['id'] : '';
                if ($sourceId === '') {
                    $counts['skipped']++;
                    continue;
                }

                if (isset($row['is_deleted']) && (int) $row['is_deleted'] === 1) {
                    $counts['skipped']++;
                    continue;
                }

                $values = [];
                foreach ($row as $column => $raw) {
                    $column = (string) $column;
                    if (in_array($column, self::SKIP_COLUMNS, true)) {
                        continue;
                    }
                    $field = $fieldsByColumn[$column] ?? $fieldsByColumn[Str::snake($column)] ?? null;
                    if ($field === null) {
                        continue;
                    }
                    $values[$field->name] = $this->castValue($field, $raw);
                }

                $values = $this->remapRelationshipValues($entity, $values);

                // Only link parent when remap produced a real dyn_records UUID.
                // Unmapped legacy FKs (deleted/missing parents) stay in values_json as source ids.
                $parentRecordId = null;
                foreach ($parentFieldNames as $pf) {
                    $candidate = $values[$pf] ?? null;
                    if (is_string($candidate) && $this->isUuid($candidate)) {
                        $parentRecordId = $candidate;
                        break;
                    }
                }

                $status = isset($row['status']) ? (string) $row['status'] : ($values['status'] ?? null);
                $title = $this->guessTitle($entity, $values, $row);

                $existingMap = AtcImportIdMap::query()
                    ->where('source_table', $entity->source_linked_table ?: ('dat_'.$entity->slug))
                    ->where('source_id', $sourceId)
                    ->where('target_type', 'record')
                    ->first();

                if ($existingMap !== null) {
                    $record = DynRecord::query()->find($existingMap->target_uuid);
                    if ($record instanceof DynRecord) {
                        $record->status = $status;
                        $record->title = $title;
                        $record->values_json = $values;
                        $record->parent_record_id = $parentRecordId;
                        $record->source_external_id = $sourceId;
                        $record->is_deleted = false;
                        $record->deleted_at = null;
                        $record->save();
                        $this->records->rebuildIndexes($entity, $record);
                        $counts['records']++;
                        continue;
                    }
                }

                $record = DynRecord::query()->create([
                    'entity_id' => $entity->id,
                    'status' => $status,
                    'title' => $title,
                    'values_json' => $values,
                    'parent_record_id' => $parentRecordId,
                    'source_external_id' => $sourceId,
                    'is_deleted' => false,
                    'created_by' => null,
                    'updated_by' => null,
                ]);

                AtcImportIdMap::query()->updateOrCreate(
                    [
                        'source_table' => $entity->source_linked_table ?: ('dat_'.$entity->slug),
                        'source_id' => $sourceId,
                        'target_type' => 'record',
                    ],
                    ['target_uuid' => $record->id]
                );

                $this->records->rebuildIndexes($entity, $record);
                $counts['records']++;
            }
        });

        return $counts;
    }

    /**
     * @return array<string, DynField>
     */
    private function buildColumnFieldMap(DynEntity $entity): array
    {
        $map = [];
        foreach ($entity->fields as $field) {
            $map[$field->name] = $field;
            if ($field->system_column) {
                $map[(string) $field->system_column] = $field;
            }
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    private function parentLinkFieldNames(string $slug): array
    {
        return match ($slug) {
            'tower_sites', 'purchase_transactions', 'products', 'warehouses', 'suppliers', 'vendors',
            'chart_of_accounts', 'fiscal_periods', 'customers', 'bank_accounts', 'capex_budgets' => [],
            'purchase_transaction_items' => ['purchase_transaction_id'],
            'sales_transaction_items' => ['sales_transaction_id'],
            'bank_transactions' => ['bank_account_id'],
            default => ['tower_site_id', 'site_id', 'parent_id'],
        };
    }

    private function castValue(DynField $field, ?string $raw): mixed
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match ($field->type) {
            DynFieldType::NUMBER, DynFieldType::DECIMAL => is_numeric($raw) ? $raw + 0 : $raw,
            DynFieldType::BOOLEAN => in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], true),
            DynFieldType::MULTISELECT => $this->decodeJsonArray($raw) ?? [$raw],
            default => $raw,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function remapRelationshipValues(DynEntity $entity, array $values): array
    {
        foreach ($entity->fields as $field) {
            if ($field->type !== DynFieldType::RELATIONSHIP) {
                // Still remap known FK-style fields even if typed as number/text in dump
                if (! str_ends_with($field->name, '_id')) {
                    continue;
                }
            }

            if (! isset($values[$field->name]) || $values[$field->name] === null || $values[$field->name] === '') {
                continue;
            }

            $sourceId = (string) $values[$field->name];
            $targetSlug = $this->guessRelatedSlug($field->name, $field->target_entity_id);
            if ($targetSlug === null) {
                continue;
            }

            $relatedEntity = DynEntity::query()->where('slug', $targetSlug)->first();
            if ($relatedEntity === null) {
                continue;
            }

            $map = AtcImportIdMap::query()
                ->where('source_table', $relatedEntity->source_linked_table ?: ('dat_'.$relatedEntity->slug))
                ->where('source_id', $sourceId)
                ->where('target_type', 'record')
                ->first();

            if ($map !== null) {
                $values[$field->name] = $map->target_uuid;
            }
        }

        return $values;
    }

    private function guessRelatedSlug(string $fieldName, mixed $targetEntityId): ?string
    {
        if (is_string($targetEntityId) && $targetEntityId !== '') {
            $entity = DynEntity::query()->find($targetEntityId);
            if ($entity instanceof DynEntity) {
                return $entity->slug;
            }
        }

        return match ($fieldName) {
            'tower_site_id', 'site_id' => 'tower_sites',
            'vendor_id' => 'vendors',
            'supplier_id' => 'suppliers',
            'territory_id' => 'territories',
            'project_team_id', 'team_id' => 'project_teams',
            'lessor_id' => 'lessors',
            'branch_id' => 'branches',
            'electric_utility_id' => 'electric_utilities',
            'milestone_id', 'milestone_catalogue_id' => 'milestone_catalogue',
            'construction_project_id' => 'construction_projects',
            'product_id' => 'products',
            'warehouse_id', 'to_warehouse_id' => 'warehouses',
            'purchase_transaction_id' => 'purchase_transactions',
            'sales_transaction_id' => 'sales_transactions',
            'customer_id' => 'customers',
            'account_id' => 'chart_of_accounts',
            'bank_account_id' => 'bank_accounts',
            'reconciliation_id' => 'bank_reconciliations',
            'fiscal_period' => 'fiscal_periods',
            'cme_vendor_id' => 'vendors',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<string, string|null>  $row
     */
    private function guessTitle(DynEntity $entity, array $values, array $row): ?string
    {
        foreach (['site_name', 'project_name', 'name', 'title', 'vendor_name', 'supplier_name', 'product_name', 'purchase_number', 'customer_name', 'account_name', 'account_code', 'asset_name', 'asset_code', 'transaction_number', 'payment_number', 'period_name', 'bank_name', 'territory_name', 'team_name', 'milestone_name', 'branch_name', 'site_code', 'code'] as $key) {
            if (! empty($values[$key]) && is_scalar($values[$key])) {
                return substr((string) $values[$key], 0, 255);
            }
            if (! empty($row[$key]) && is_scalar($row[$key])) {
                return substr((string) $row[$key], 0, 255);
            }
        }

        return $entity->name.' #'.($row['id'] ?? '');
    }

    /**
     * @return list<mixed>|null
     */
    private function decodeJsonArray(string $raw): ?array
    {
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function wireTowerSiteRelatedTabs(): void
    {
        $entity = DynEntity::query()->where('slug', 'tower_sites')->first();
        if ($entity === null) {
            return;
        }

        $entity->related_tabs_json = AtcPmRelatedTabs::forTowerSites();
        $entity->save();
    }

    private function wirePurchaseTransactionRelatedTabs(): void
    {
        $entity = DynEntity::query()->where('slug', 'purchase_transactions')->first();
        if ($entity === null) {
            return;
        }

        $entity->related_tabs_json = AtcProcurementPack::forPurchaseTransactions();
        $entity->save();
    }

    private function wireSalesAndBankRelatedTabs(): void
    {
        $sales = DynEntity::query()->where('slug', 'sales_transactions')->first();
        if ($sales !== null) {
            $sales->related_tabs_json = AtcFinancePack::forSalesTransactions();
            $sales->save();
        }

        $banks = DynEntity::query()->where('slug', 'bank_accounts')->first();
        if ($banks !== null) {
            $banks->related_tabs_json = AtcFinancePack::forBankAccounts();
            $banks->save();
        }
    }

    private function isUuid(string $value): bool
    {
        return Str::isUuid($value);
    }
}
