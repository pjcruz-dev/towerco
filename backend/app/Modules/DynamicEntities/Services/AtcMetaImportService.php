<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\AtcImportIdMap;
use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Support\AtcFinancePack;
use App\Modules\DynamicEntities\Support\AtcPmRelatedTabs;
use App\Modules\DynamicEntities\Support\AtcPrintTemplates;
use App\Modules\DynamicEntities\Support\AtcProcurementPack;
use App\Modules\DynamicEntities\Support\AtcTicketingPack;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\DynamicEntities\Support\DynModulePack;
use App\Modules\DynamicEntities\Support\SqlDumpReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Imports Metacoresoft entities/fields metadata into dyn_* tables.
 * Expects a SQL dump path; uses lightweight regex extraction (not full SQL parser).
 */
final class AtcMetaImportService
{
    /** @var array<string, string> */
    private const SLUG_PACK = [
        'tower_sites' => DynModulePack::PM,
        'construction_projects' => DynModulePack::PM,
        'construction_activities' => DynModulePack::PM,
        'saq_trackers' => DynModulePack::PM,
        'power_trackers' => DynModulePack::PM,
        'postcon_trackers' => DynModulePack::PM,
        'land_leases' => DynModulePack::PM,
        'lessors' => DynModulePack::PM,
        'site_permits' => DynModulePack::PM,
        'telco_contracts' => DynModulePack::PM,
        'site_colocations' => DynModulePack::PM,
        'site_documents' => DynModulePack::PM,
        'electric_utilities' => DynModulePack::REFERENCE,
        'project_teams' => DynModulePack::REFERENCE,
        'milestone_catalogue' => DynModulePack::REFERENCE,
        'territories' => DynModulePack::REFERENCE,
        'branches' => DynModulePack::REFERENCE,
        'vendors' => DynModulePack::PROCUREMENT,
        'procurement_requests' => DynModulePack::PROCUREMENT,
        'purchase_transactions' => DynModulePack::PROCUREMENT,
        'purchase_transaction_items' => DynModulePack::PROCUREMENT,
        'products' => DynModulePack::PROCUREMENT,
        'warehouses' => DynModulePack::PROCUREMENT,
        'stock_movements' => DynModulePack::PROCUREMENT,
        'suppliers' => DynModulePack::PROCUREMENT,
        'chart_of_accounts' => DynModulePack::FINANCE,
        'general_ledger' => DynModulePack::FINANCE,
        'fiscal_periods' => DynModulePack::FINANCE,
        'bank_accounts' => DynModulePack::FINANCE,
        'bank_transactions' => DynModulePack::FINANCE,
        'bank_reconciliations' => DynModulePack::FINANCE,
        'payments_and_receipts' => DynModulePack::FINANCE,
        'capex_budgets' => DynModulePack::FINANCE,
        'site_operating_costs' => DynModulePack::FINANCE,
        'bir_form_2307_certificates' => DynModulePack::FINANCE,
        'fixed_assets' => DynModulePack::FINANCE,
        'customers' => DynModulePack::FINANCE,
        'sales_transactions' => DynModulePack::FINANCE,
        'sales_transaction_items' => DynModulePack::FINANCE,
        'ticket_categories' => DynModulePack::TICKETING,
        'site_tickets' => DynModulePack::TICKETING,
        'ticket_activities' => DynModulePack::TICKETING,
    ];

    public function __construct(
        private readonly SqlDumpReader $reader,
    ) {}

    /**
     * @return array{entities: int, fields: int, skipped: int, field_groups?: int, field_groups_entities?: int}
     */
    public function importFromDump(string $dumpPath): array
    {
        $sql = $this->reader->readFile($dumpPath);

        $entities = $this->reader->extractInsertRows($sql, 'entities');
        $fields = $this->reader->extractInsertRows($sql, 'fields');

        $stats = ['entities' => 0, 'fields' => 0, 'skipped' => 0];

        DB::transaction(function () use ($entities, $fields, &$stats): void {
            $entityIdMap = [];

            foreach ($entities as $row) {
                $sourceId = (string) ($row['id'] ?? '');
                $slug = (string) ($row['slug'] ?? '');
                $name = (string) ($row['name'] ?? '');
                if ($sourceId === '' || $slug === '' || $name === '') {
                    $stats['skipped']++;
                    continue;
                }

                if (in_array($slug, ['users_system', 'audit_logs'], true)) {
                    $stats['skipped']++;
                    continue;
                }

                $pack = self::SLUG_PACK[$slug] ?? DynModulePack::REFERENCE;
                $relatedTabs = match ($slug) {
                    'tower_sites' => AtcPmRelatedTabs::forTowerSites(),
                    'purchase_transactions' => AtcProcurementPack::forPurchaseTransactions(),
                    'sales_transactions' => AtcFinancePack::forSalesTransactions(),
                    'bank_accounts' => AtcFinancePack::forBankAccounts(),
                    'site_tickets' => AtcTicketingPack::forSiteTickets(),
                    default => null,
                };

                $existing = DynEntity::query()->where('slug', $slug)->first();
                $entity = DynEntity::query()->updateOrCreate(
                    ['slug' => $slug],
                    [
                        'name' => $name,
                        'description' => $row['description'] ?? null,
                        'module_pack' => $pack,
                        'storage_mode' => 'json',
                        'is_location_based' => (bool) ((int) ($row['is_location_based'] ?? 0)),
                        'source_linked_table' => isset($row['linked_table']) && $row['linked_table'] !== ''
                            ? (string) $row['linked_table']
                            : 'dat_'.$slug,
                        'related_tabs_json' => $relatedTabs ?? $existing?->related_tabs_json,
                        'print_settings_json' => $existing?->print_settings_json
                            ?? AtcPrintTemplates::defaultsForSlug($slug),
                        'sort_order' => (int) $sourceId,
                        'is_active' => true,
                    ]
                );

                AtcImportIdMap::query()->updateOrCreate(
                    [
                        'source_table' => 'entities',
                        'source_id' => $sourceId,
                        'target_type' => 'entity',
                    ],
                    ['target_uuid' => $entity->id]
                );

                $entityIdMap[$sourceId] = $entity->id;
                $stats['entities']++;
            }

            foreach ($fields as $row) {
                $sourceEntityId = (string) ($row['entity_id'] ?? '');
                $fieldName = (string) ($row['name'] ?? '');
                $label = (string) ($row['label'] ?? $fieldName);
                if ($sourceEntityId === '' || $fieldName === '' || ! isset($entityIdMap[$sourceEntityId])) {
                    $stats['skipped']++;
                    continue;
                }

                $type = $this->mapFieldType((string) ($row['type'] ?? 'text'));
                $options = null;
                if (! empty($row['options']) && is_string($row['options'])) {
                    $decoded = json_decode($row['options'], true);
                    $options = is_array($decoded) ? $decoded : null;
                }

                $targetEntityId = null;
                $relatedSourceId = (string) ($row['related_entity_id'] ?? $row['target_entity_id'] ?? '');
                if ($relatedSourceId !== '' && isset($entityIdMap[$relatedSourceId])) {
                    $targetEntityId = $entityIdMap[$relatedSourceId];
                }

                $normalizedName = Str::snake($fieldName);
                $isSystem = (bool) ((int) ($row['is_system_field'] ?? 0));
                $listChrome = in_array($normalizedName, ['id', 'actions', 'print'], true);
                if ($isSystem && $listChrome) {
                    $showInTable = array_key_exists('show_in_table', $row)
                        ? (bool) ((int) $row['show_in_table'])
                        : true;
                } elseif ($isSystem) {
                    $showInTable = false;
                } else {
                    $showInTable = (bool) ((int) ($row['show_in_table'] ?? 0));
                }

                $field = DynField::query()->updateOrCreate(
                    [
                        'entity_id' => $entityIdMap[$sourceEntityId],
                        'name' => $normalizedName,
                    ],
                    [
                        'label' => $label !== '' ? $label : $fieldName,
                        'type' => $type,
                        'is_required' => (bool) ((int) ($row['is_required'] ?? 0)),
                        'is_system_field' => $isSystem,
                        'system_column' => $row['system_column'] ?? $fieldName,
                        'show_in_table' => $showInTable,
                        'is_filterable' => (bool) ((int) ($row['is_filterable'] ?? 0)),
                        'options_json' => $options,
                        'target_entity_id' => $targetEntityId,
                        'placeholder' => $row['placeholder'] ?? null,
                        'formula_definition' => $row['formula_definition'] ?? null,
                        'column_span' => max(1, min(12, (int) ($row['column_span'] ?? 6))),
                        'field_order' => (int) ($row['field_order'] ?? 10),
                        'is_virtual' => (bool) ((int) ($row['is_virtual'] ?? 0)),
                    ]
                );

                if (! empty($row['id'])) {
                    AtcImportIdMap::query()->updateOrCreate(
                        [
                            'source_table' => 'fields',
                            'source_id' => (string) $row['id'],
                            'target_type' => 'field',
                        ],
                        ['target_uuid' => $field->id]
                    );
                }

                $stats['fields']++;
            }
        });

        app(DynPrintLayoutService::class)->ensureAllKnown();
        $groupStats = app(AtcFieldGroupImportService::class)->importFromDump($dumpPath, false);
        $stats['field_groups'] = $groupStats['groups'];
        $stats['field_groups_entities'] = $groupStats['entities'];

        return $stats;
    }

    private function mapFieldType(string $source): string
    {
        $source = strtolower(trim($source));

        return match (true) {
            in_array($source, ['number', 'decimal', 'currency', 'integer'], true) => DynFieldType::NUMBER,
            in_array($source, ['date'], true) => DynFieldType::DATE,
            in_array($source, ['datetime', 'datetime-local', 'timestamp'], true) => DynFieldType::DATETIME,
            in_array($source, ['select', 'dropdown', 'status'], true) => DynFieldType::SELECT,
            in_array($source, ['multiselect', 'tags'], true) => DynFieldType::MULTISELECT,
            in_array($source, ['textarea', 'longtext', 'richtext'], true) => DynFieldType::TEXTAREA,
            in_array($source, ['checkbox', 'boolean', 'toggle'], true) => DynFieldType::BOOLEAN,
            in_array($source, ['relationship', 'related_entity', 'lookup'], true) => DynFieldType::RELATIONSHIP,
            in_array($source, ['file', 'image', 'attachment'], true) => DynFieldType::FILE,
            in_array($source, ['email'], true) => DynFieldType::EMAIL,
            in_array($source, ['phone', 'tel'], true) => DynFieldType::PHONE,
            in_array($source, ['automatic_id', 'auto_id', 'autoid', 'auto_number', 'autonumber'], true) => DynFieldType::AUTOMATIC_ID,
            default => DynFieldType::TEXT,
        };
    }
}
