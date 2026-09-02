<?php

declare(strict_types=1);

namespace App\Modules\DynamicEntities\Services;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Models\DynField;
use App\Modules\DynamicEntities\Models\DynRecord;
use App\Modules\DynamicEntities\Support\DynFieldType;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeds demo Dynamic Entity records for empty packs, linked to existing tower_sites.
 *
 * Sets both values_json.tower_site_id and parent_record_id so related tabs and
 * Cost & Capital reports (Materials Issued, Cost vs Budget, Capex & Opex) light up.
 */
final class AtcDemoRelatedSeedService
{
    private const SOURCE_PREFIX = 'demo:';

    /**
     * Global / reference masters (created once, not per site).
     *
     * @var list<string>
     */
    private const MASTER_SLUGS = [
        'territories',
        'project_teams',
        'milestone_catalogue',
        'branches',
        'electric_utilities',
        'vendors',
        'lessors',
        'suppliers',
        'warehouses',
        'products',
        'chart_of_accounts',
        'fiscal_periods',
        'customers',
        'bank_accounts',
        'capex_budgets',
        'bir_form_2307_certificates',
    ];

    /**
     * Per-site children (Cost & Capital / Procurement / Finance / PM / Ticketing).
     *
     * @var list<string>
     */
    private const SITE_LINKED_SLUGS = [
        'construction_projects',
        'construction_activities',
        'saq_trackers',
        'power_trackers',
        'postcon_trackers',
        'land_leases',
        'site_permits',
        'telco_contracts',
        'site_colocations',
        'site_documents',
        'procurement_requests',
        'purchase_transactions',
        'stock_movements',
        'site_operating_costs',
        'fixed_assets',
        'general_ledger',
        'payments_and_receipts',
        'site_tickets',
    ];

    public function __construct(
        private readonly DynRecordService $records,
        private readonly AtcTicketingPackSeedService $ticketingPack,
    ) {}

    /**
     * @return array{
     *   sites: int,
     *   masters_created: int,
     *   masters_skipped: int,
     *   linked_created: int,
     *   linked_skipped: int,
     *   missing_entities: list<string>,
     *   sample_errors: list<string>,
     *   dry_run: bool
     * }
     */
    public function seed(
        TenantUser $actor,
        int $limitSites = 25,
        int $perSite = 1,
        bool $dryRun = false,
        bool $ensureTicketingSchema = true,
    ): array {
        $stats = [
            'sites' => 0,
            'masters_created' => 0,
            'masters_skipped' => 0,
            'linked_created' => 0,
            'linked_skipped' => 0,
            'missing_entities' => [],
            'sample_errors' => [],
            'dry_run' => $dryRun,
        ];

        if ($ensureTicketingSchema && ! $dryRun) {
            $this->ticketingPack->seed((string) $actor->id);
        }

        $entities = DynEntity::query()
            ->where('is_active', true)
            ->with('fields')
            ->get()
            ->keyBy('slug');

        $missing = [];
        foreach ([...self::MASTER_SLUGS, ...self::SITE_LINKED_SLUGS] as $slug) {
            if (! $entities->has($slug)) {
                $missing[] = $slug;
            }
        }
        $stats['missing_entities'] = array_values(array_unique($missing));

        $masterIds = [];
        foreach (self::MASTER_SLUGS as $slug) {
            $entity = $entities->get($slug);
            if (! $entity instanceof DynEntity) {
                continue;
            }
            $wanted = $this->masterBlueprints($slug);
            foreach ($wanted as $index => $blueprint) {
                $externalId = self::SOURCE_PREFIX.'master:'.$slug.':'.$index;
                if ($this->existsByExternal($entity, $externalId)) {
                    $stats['masters_skipped']++;
                    $existing = DynRecord::query()
                        ->where('entity_id', $entity->id)
                        ->where('source_external_id', $externalId)
                        ->where('is_deleted', false)
                        ->first();
                    if ($existing) {
                        $masterIds[$slug][] = (string) $existing->id;
                    }

                    continue;
                }
                if ($dryRun) {
                    $stats['masters_created']++;
                    $masterIds[$slug][] = 'dry-run';

                    continue;
                }
                try {
                    $record = $this->createRecord(
                        $entity,
                        $actor,
                        $externalId,
                        $blueprint['title'],
                        $blueprint['status'] ?? 'Active',
                        $blueprint['values'],
                        null,
                    );
                } catch (\Throwable) {
                    $stats['masters_skipped']++;

                    continue;
                }
                $masterIds[$slug][] = (string) $record->id;
                $stats['masters_created']++;
            }
        }

        $sitesEntity = $entities->get('tower_sites');
        if (! $sitesEntity instanceof DynEntity) {
            return $stats;
        }

        $sites = DynRecord::query()
            ->where('entity_id', $sitesEntity->id)
            ->where('is_deleted', false)
            ->orderBy('title')
            ->limit(max(1, min(500, $limitSites)))
            ->get();

        $stats['sites'] = $sites->count();
        $perSite = max(1, min(5, $perSite));

        foreach ($sites as $siteIndex => $site) {
            $siteLabel = $this->siteLabel($site);
            $siteCode = (string) (($site->values_json['site_code'] ?? '') ?: substr((string) $site->id, 0, 8));
            $region = (string) (($site->values_json['region'] ?? '') ?: 'NCR');

            for ($n = 1; $n <= $perSite; $n++) {
                $seed = $siteIndex * 17 + $n * 3;
                $createdParents = [];

                foreach (self::SITE_LINKED_SLUGS as $slug) {
                    $entity = $entities->get($slug);
                    if (! $entity instanceof DynEntity) {
                        continue;
                    }

                    $externalId = $this->demoExternalId($slug, (string) $site->id, $n);
                    if ($this->existsByExternal($entity, $externalId)) {
                        $stats['linked_skipped']++;
                        $existing = DynRecord::query()
                            ->where('entity_id', $entity->id)
                            ->where('source_external_id', $externalId)
                            ->where('is_deleted', false)
                            ->first();
                        if ($existing) {
                            $createdParents[$slug] = (string) $existing->id;
                        }

                        continue;
                    }

                    $overrides = $this->siteLinkedOverrides(
                        $slug,
                        (string) $site->id,
                        $siteLabel,
                        $siteCode,
                        $region,
                        $seed,
                        $n,
                        $masterIds,
                        $createdParents,
                    );

                    if ($slug === 'construction_activities' && empty($overrides['construction_project_id'])) {
                        $overrides['construction_project_id'] = $this->firstChildForSite('construction_projects', (string) $site->id)
                            ?? ($createdParents['construction_projects'] ?? null);
                    }

                    if ($slug === 'site_tickets') {
                        $catId = $this->firstRecordId('ticket_categories');
                        if ($catId !== null) {
                            $overrides['category_id'] = $catId;
                        }
                    }

                    if ($slug === 'payments_and_receipts' && empty($overrides['account_id'])) {
                        $overrides['account_id'] = $this->firstRecordId('chart_of_accounts');
                    }

                    if ($slug === 'site_documents' && empty($overrides['lessor_id'])) {
                        $overrides['lessor_id'] = $this->firstRecordId('lessors');
                    }

                    if ($dryRun) {
                        $stats['linked_created']++;
                        $createdParents[$slug] = 'dry-run';

                        continue;
                    }

                    $title = (string) ($overrides['_title'] ?? ($siteLabel.' · '.Str::headline(str_replace('_', ' ', $slug))));
                    unset($overrides['_title']);
                    $status = (string) ($overrides['_status'] ?? 'Active');
                    unset($overrides['_status']);

                    try {
                        $record = $this->createRecord(
                            $entity,
                            $actor,
                            $externalId,
                            $title,
                            $status,
                            $overrides,
                            (string) $site->id,
                        );
                    } catch (\Throwable $e) {
                        $stats['linked_skipped']++;
                        if (count($stats['sample_errors']) < 8) {
                            $msg = $e->getMessage();
                            if ($e instanceof \Illuminate\Validation\ValidationException) {
                                $msg = collect($e->errors())->flatten()->implode('; ');
                            }
                            $stats['sample_errors'][] = $slug.': '.$msg;
                        }

                        continue;
                    }
                    $createdParents[$slug] = (string) $record->id;
                    $stats['linked_created']++;

                    if ($slug === 'purchase_transactions') {
                        $stats['linked_created'] += $this->seedPurchaseItems(
                            $entities->get('purchase_transaction_items'),
                            $actor,
                            (string) $record->id,
                            $externalId,
                            $siteLabel,
                            $seed,
                            $masterIds,
                            $dryRun,
                        );
                    }

                    if ($slug === 'site_tickets') {
                        $stats['linked_created'] += $this->seedTicketActivities(
                            $entities->get('ticket_activities'),
                            $actor,
                            (string) $record->id,
                            $externalId,
                            $siteLabel,
                            $dryRun,
                        );
                    }
                }
            }
        }

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function createRecord(
        DynEntity $entity,
        TenantUser $actor,
        string $externalId,
        string $title,
        string $status,
        array $values,
        ?string $parentRecordId,
    ): DynRecord {
        $filled = $this->fillMissingRequired($entity, $values, $title, $parentRecordId);

        return $this->records->create($entity, [
            'title' => $title,
            'status' => $status,
            'values' => $filled,
            'parent_record_id' => $parentRecordId,
            'source_external_id' => $externalId,
        ], $actor, false);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function fillMissingRequired(DynEntity $entity, array $values, string $title, ?string $siteId): array
    {
        $entity->loadMissing('fields');

        if ($siteId !== null) {
            if ($entity->fields->contains(fn (DynField $f): bool => $f->name === 'tower_site_id')) {
                $values['tower_site_id'] = $siteId;
            }
            if ($entity->fields->contains(fn (DynField $f): bool => $f->name === 'site_id')) {
                $values['site_id'] = $siteId;
            }
        }

        foreach ($entity->fields as $field) {
            if ($field->is_virtual || $field->is_system_field) {
                continue;
            }
            $name = (string) $field->name;
            if (array_key_exists($name, $values) && $values[$name] !== null && $values[$name] !== '') {
                continue;
            }

            $shouldFill = $field->is_required || $this->isReportOrDisplayField($name);
            if (! $shouldFill) {
                continue;
            }

            $auto = $this->autoValue($field, $title, $siteId, $values);
            if ($auto !== null && $auto !== '') {
                $values[$name] = $auto;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    private function autoValue(DynField $field, string $title, ?string $siteId, array $values): mixed
    {
        $name = (string) $field->name;
        $type = (string) $field->type;

        if (in_array($name, ['tower_site_id', 'site_id'], true)) {
            return $siteId;
        }

        if ($type === DynFieldType::RELATIONSHIP) {
            if (isset($values[$name]) && $values[$name]) {
                return $values[$name];
            }
            $targetId = $field->target_entity_id ? (string) $field->target_entity_id : null;
            if ($targetId) {
                $related = DynRecord::query()
                    ->where('entity_id', $targetId)
                    ->where('is_deleted', false)
                    ->orderBy('created_at')
                    ->value('id');
                if ($related) {
                    return (string) $related;
                }
            }

            return null;
        }

        $options = $this->choiceOptions($field->options_json);
        if (in_array($type, [DynFieldType::SELECT, DynFieldType::MULTISELECT], true) && $options !== []) {
            return $type === DynFieldType::MULTISELECT ? [$options[0]] : $options[0];
        }

        return match (true) {
            $name === 'reference' => 'DEMO-REF-'.strtoupper(substr(md5($title), 0, 6)),
            $name === 'ticket_number' => 'TKT-'.strtoupper(substr(md5($title), 0, 8)),
            $name === 'subject' => $title,
            $name === 'asset_code' => 'FA-'.strtoupper(substr(md5($title), 0, 8)),
            $name === 'asset_name' => $title,
            $name === 'category' || $name === 'cost_category' => $options[0] ?? 'General',
            $name === 'discipline' => $options[0] ?? 'Civil',
            $name === 'type' && str_contains(strtolower((string) $field->label), 'direction') => 'issue',
            $name === 'type' => $options[0] ?? 'issue',
            $name === 'movement_type' => 'issue',
            $name === 'period_month' => now()->format('Y-m'),
            $name === 'acquisition_date', $name === 'movement_date' => now()->toDateString(),
            $name === 'useful_life_years' => 15,
            str_contains($name, 'email') => 'demo@example.local',
            str_contains($name, 'phone') => '+63 917 000 0000',
            in_array($name, ['site_name', 'project_name', 'name', 'title', 'account_name', 'description'], true) => $title,
            str_ends_with($name, '_code') || $name === 'code' => 'DEMO-'.strtoupper(substr(md5($title.$name), 0, 6)),
            in_array($type, [DynFieldType::NUMBER, DynFieldType::DECIMAL], true) => $this->defaultNumber($name),
            $type === DynFieldType::BOOLEAN => true,
            $type === DynFieldType::DATE => now()->toDateString(),
            $type === DynFieldType::DATETIME => now()->format('Y-m-d H:i:s'),
            $type === DynFieldType::TEXTAREA => 'Demo seed data for '.$title,
            $name === 'status' => $options[0] ?? 'Active',
            default => Str::limit($title, 80, ''),
        };
    }

    private function defaultNumber(string $name): float|int
    {
        return match (true) {
            str_contains($name, 'budget') => 2_500_000,
            str_contains($name, 'acquisition_cost'),
            str_contains($name, 'total_cost'),
            str_contains($name, 'amount'),
            str_contains($name, 'debit') => 185_000,
            str_contains($name, 'credit') => 0,
            str_contains($name, 'quantity') => 12,
            str_contains($name, 'unit_cost'),
            str_contains($name, 'unit_price') => 4_500,
            str_contains($name, 'sla') => 24,
            default => 1,
        };
    }

    private function isReportOrDisplayField(string $name): bool
    {
        return in_array($name, [
            'tower_site_id', 'site_id', 'budget', 'total_cost', 'acquisition_cost', 'amount',
            'cost_category', 'period_month', 'movement_type', 'movement_date', 'quantity',
            'product_id', 'warehouse_id', 'debit_amount', 'credit_amount', 'cost_classification',
            'project_name', 'site_name', 'name', 'code', 'priority', 'ticket_type', 'category_id',
            'reported_at', 'due_at', 'notes', 'status',
        ], true) || str_contains($name, 'cost') || str_contains($name, 'budget') || str_contains($name, 'amount');
    }

    /**
     * @return list<string>
     */
    private function choiceOptions(mixed $optionsJson): array
    {
        if (is_array($optionsJson)) {
            if (array_is_list($optionsJson)) {
                return array_values(array_filter(array_map('strval', $optionsJson)));
            }
            if (isset($optionsJson['choices']) && is_array($optionsJson['choices'])) {
                return array_values(array_filter(array_map('strval', $optionsJson['choices'])));
            }
        }

        return [];
    }

    private function existsByExternal(DynEntity $entity, string $externalId): bool
    {
        return DynRecord::query()
            ->where('entity_id', $entity->id)
            ->where('source_external_id', $externalId)
            ->where('is_deleted', false)
            ->exists();
    }

    private function siteLabel(DynRecord $site): string
    {
        $values = $site->values_json ?? [];
        foreach (['site_name', 'site_code', 'name'] as $key) {
            if (! empty($values[$key]) && is_scalar($values[$key])) {
                return (string) $values[$key];
            }
        }

        return (string) ($site->title ?: 'Site '.substr((string) $site->id, 0, 8));
    }

    /**
     * @return list<array{title: string, status?: string, values: array<string, mixed>}>
     */
    private function masterBlueprints(string $slug): array
    {
        return match ($slug) {
            'territories' => [
                ['title' => 'Demo North', 'values' => ['name' => 'Demo North', 'code' => 'DEMO-N']],
                ['title' => 'Demo South', 'values' => ['name' => 'Demo South', 'code' => 'DEMO-S']],
            ],
            'project_teams' => [
                ['title' => 'Demo Build Team', 'values' => ['name' => 'Demo Build Team', 'code' => 'DEMO-TEAM']],
            ],
            'milestone_catalogue' => [
                ['title' => 'RFTI', 'values' => ['name' => 'RFTI', 'code' => 'RFTI']],
                ['title' => 'On Air', 'values' => ['name' => 'On Air', 'code' => 'OA']],
            ],
            'branches' => [
                ['title' => 'Demo Branch Manila', 'values' => ['name' => 'Demo Branch Manila', 'code' => 'DEMO-MNL']],
            ],
            'electric_utilities' => [
                ['title' => 'Demo Electric Cooperative', 'values' => [
                    'utility_name' => 'Demo Electric Cooperative',
                    'utility_type' => 'Electric Cooperative',
                    'region' => 'MIN',
                    'coverage_area' => 'Demo coverage area',
                    'contact_number' => '+63 917 000 1111',
                ]],
                ['title' => 'Demo Private Utility', 'values' => [
                    'utility_name' => 'Demo Private Utility',
                    'utility_type' => 'Private Utility',
                    'region' => 'NLZ',
                    'coverage_area' => 'Demo metro coverage',
                    'contact_number' => '+63 917 000 2222',
                ]],
            ],
            'vendors', 'suppliers' => [
                ['title' => 'Demo Steel Supplier', 'values' => ['name' => 'Demo Steel Supplier', 'code' => 'DEMO-VND-01']],
                ['title' => 'Demo Civil Contractor', 'values' => ['name' => 'Demo Civil Contractor', 'code' => 'DEMO-VND-02']],
            ],
            'lessors' => [
                ['title' => 'Demo Landowner', 'values' => ['name' => 'Demo Landowner', 'code' => 'DEMO-LSR']],
            ],
            'warehouses' => [
                ['title' => 'Demo Central Warehouse', 'values' => ['name' => 'Demo Central Warehouse', 'warehouse_name' => 'Demo Central Warehouse', 'code' => 'DEMO-WH']],
            ],
            'products' => [
                ['title' => 'Galvanized Bolt Set', 'values' => ['name' => 'Galvanized Bolt Set', 'product_name' => 'Galvanized Bolt Set', 'code' => 'DEMO-SKU-01', 'unit_cost' => 4500]],
                ['title' => 'Feeder Cable 50m', 'values' => ['name' => 'Feeder Cable 50m', 'product_name' => 'Feeder Cable 50m', 'code' => 'DEMO-SKU-02', 'unit_cost' => 12500]],
            ],
            'chart_of_accounts' => [
                ['title' => '5100 Capex Towers', 'values' => ['name' => '5100 Capex Towers', 'code' => '5100', 'classification' => 'Capex', 'cost_classification' => 'Capex']],
                ['title' => '6100 Site Opex', 'values' => ['name' => '6100 Site Opex', 'code' => '6100', 'classification' => 'Opex', 'cost_classification' => 'Opex']],
                ['title' => '1020 Cash in Bank', 'values' => ['name' => '1020 Cash in Bank', 'code' => '1020', 'classification' => 'Asset']],
            ],
            'fiscal_periods' => [
                ['title' => 'FP-'.now()->format('Y').'-Q1', 'status' => 'Open', 'values' => [
                    'period_name' => 'FP-'.now()->format('Y').'-Q1',
                    'start_date' => now()->startOfYear()->toDateString(),
                    'end_date' => now()->startOfYear()->addMonths(3)->subDay()->toDateString(),
                    'status' => 'Open',
                ]],
                ['title' => 'FP-'.now()->format('Y').'-Q2', 'status' => 'Open', 'values' => [
                    'period_name' => 'FP-'.now()->format('Y').'-Q2',
                    'start_date' => now()->startOfYear()->addMonths(3)->toDateString(),
                    'end_date' => now()->startOfYear()->addMonths(6)->subDay()->toDateString(),
                    'status' => 'Open',
                ]],
                ['title' => 'FP-'.now()->subYear()->format('Y').'-H2', 'status' => 'Closed', 'values' => [
                    'period_name' => 'FP-'.now()->subYear()->format('Y').'-H2',
                    'start_date' => now()->subYear()->startOfYear()->addMonths(6)->toDateString(),
                    'end_date' => now()->subYear()->endOfYear()->toDateString(),
                    'status' => 'Closed',
                ]],
            ],
            'customers' => [
                ['title' => 'Demo Telco Customer', 'values' => ['name' => 'Demo Telco Customer', 'code' => 'DEMO-CUST']],
            ],
            'bank_accounts' => [
                ['title' => 'Demo Operating Account', 'values' => [
                    'account_name' => 'Demo Operating Account',
                    'account_number' => '0012345678',
                    'bank_name' => 'Demo Bank',
                    'status' => 'Active',
                    'current_balance' => 1_250_000,
                ]],
            ],
            'capex_budgets' => [
                ['title' => 'BGT-'.now()->format('Y'), 'status' => 'Approved', 'values' => [
                    'budget_code' => 'BGT-'.now()->format('Y'),
                    'fiscal_year' => (int) now()->format('Y'),
                    'target_towers' => 50,
                    'total_budget' => 148_000_000,
                    'allocated_budget' => 137_000_000,
                    'spent_budget' => 95_000_000,
                    'status' => 'Approved',
                ]],
                ['title' => 'BGT-'.now()->subYear()->format('Y'), 'status' => 'Closed', 'values' => [
                    'budget_code' => 'BGT-'.now()->subYear()->format('Y'),
                    'fiscal_year' => (int) now()->subYear()->format('Y'),
                    'target_towers' => 84,
                    'total_budget' => 120_000_000,
                    'allocated_budget' => 118_000_000,
                    'spent_budget' => 118_000_000,
                    'status' => 'Closed',
                ]],
            ],
            'bir_form_2307_certificates' => [
                ['title' => '2307-DEMO-0001', 'status' => 'Posted', 'values' => [
                    'control_number' => '2307-DEMO-0001',
                    'period_from' => now()->startOfQuarter()->toDateString(),
                    'period_to' => now()->endOfQuarter()->toDateString(),
                    'payee_tin' => '777-888-999-000',
                    'payee_name' => 'Demo Payee Corp',
                    'payee_address' => '123 Demo Street, Makati',
                    'payee_zip' => '1226',
                    'payor_tin' => '987-654-321-000',
                    'payor_name' => 'CAS - Alliance Towers',
                    'payor_address' => 'Alliance Towers HQ',
                    'payor_zip' => '1605',
                    'income_description' => 'Civil works progress billing',
                    'atc_code' => 'WI120',
                    'income_month1' => 250_000,
                    'income_month2' => 180_000,
                    'income_month3' => 95_000,
                    'tax_rate' => 2,
                    'tax_withheld' => 10_500,
                    'status' => 'Posted',
                ]],
                ['title' => '2307-DEMO-0002', 'status' => 'Draft', 'values' => [
                    'control_number' => '2307-DEMO-0002',
                    'period_from' => now()->startOfQuarter()->toDateString(),
                    'period_to' => now()->endOfQuarter()->toDateString(),
                    'payee_tin' => '111-222-333-000',
                    'payee_name' => 'Demo Steel Fabricators',
                    'payee_address' => '456 Fabrication Rd, Laguna',
                    'payee_zip' => '4025',
                    'payor_tin' => '987-654-321-000',
                    'payor_name' => 'CAS - Alliance Towers',
                    'payor_address' => 'Alliance Towers HQ',
                    'payor_zip' => '1605',
                    'income_description' => 'Tower steel supply',
                    'atc_code' => 'WI100',
                    'income_month1' => 400_000,
                    'tax_rate' => 1,
                    'tax_withheld' => 4_000,
                    'status' => 'Draft',
                ]],
            ],
            default => [['title' => 'Demo '.$slug, 'values' => ['name' => 'Demo '.$slug, 'code' => 'DEMO']]],
        };
    }

    /**
     * @param  array<string, list<string>>  $masterIds
     * @param  array<string, string>  $createdParents
     * @return array<string, mixed>
     */
    private function siteLinkedOverrides(
        string $slug,
        string $siteId,
        string $siteLabel,
        string $siteCode,
        string $region,
        int $seed,
        int $n,
        array $masterIds,
        array $createdParents,
    ): array {
        $budget = 1_800_000 + ($seed % 7) * 250_000;
        $actual = (int) round($budget * (0.55 + (($seed % 5) * 0.08)));
        $month = Carbon::now()->startOfMonth()->subMonths($seed % 4)->format('Y-m');

        $base = [
            'tower_site_id' => $siteId,
            'site_id' => $siteId,
            'site_name' => $siteLabel,
            'region' => $region,
        ];

        return match ($slug) {
            'stock_movements' => array_merge($base, [
                '_title' => $siteLabel.' · Issue '.$n,
                '_status' => 'Posted',
                'movement_type' => 'issue',
                'type' => 'issue',
                'reference' => 'ISS-'.$siteCode.'-'.$n,
                'movement_date' => Carbon::now()->subDays($seed % 20)->toDateString(),
                'quantity' => 4 + ($seed % 8),
                'unit_cost' => 4500,
                'total_cost' => (4 + ($seed % 8)) * 4500,
                'product_id' => $masterIds['products'][$seed % max(1, count($masterIds['products'] ?? [1]))] ?? null,
                'warehouse_id' => $masterIds['warehouses'][0] ?? null,
                'notes' => 'Materials issued to '.$siteLabel,
            ]),
            'site_operating_costs' => array_merge($base, [
                '_title' => $siteLabel.' · Opex '.$month,
                '_status' => 'Posted',
                'amount' => 18_500 + ($seed % 12) * 750,
                'cost_category' => ['Power', 'Security', 'Lease', 'Maintenance'][$seed % 4],
                'period_month' => $month,
                'name' => 'Opex '.$month,
            ]),
            'fixed_assets' => array_merge($base, [
                '_title' => $siteLabel.' · Tower Asset',
                '_status' => 'Active',
                'name' => 'Tower structure '.$siteCode,
                'asset_name' => 'Tower structure '.$siteCode,
                'asset_code' => 'FA-'.$siteCode.'-'.$n,
                'category' => 'Tower Structure',
                'acquisition_cost' => $budget,
                'acquisition_date' => Carbon::now()->subMonths(6)->toDateString(),
                'useful_life_years' => 15,
                'status' => 'Active',
                'amount' => $budget,
            ]),
            'general_ledger' => array_merge($base, [
                '_title' => $siteLabel.' · GL Capex',
                '_status' => 'Posted',
                'debit_amount' => 75_000 + ($seed % 5) * 5_000,
                'credit_amount' => 0,
                'cost_classification' => 'Capex',
                'account_id' => $masterIds['chart_of_accounts'][0] ?? null,
                'name' => 'Capex posting '.$siteCode,
            ]),
            'site_tickets' => array_merge($base, [
                '_title' => $siteLabel.' · Power alarm',
                '_status' => 'Open',
                'ticket_number' => 'TKT-'.$siteCode.'-'.$n,
                'subject' => 'Genset not auto-starting — '.$siteLabel,
                'name' => 'Genset not auto-starting',
                'ticket_type' => 'Incident',
                'priority' => ['High', 'Medium', 'Critical', 'Low'][$seed % 4],
                'reported_by' => 'Field Ops',
                'assigned_to' => 'NOC L1',
                'reported_at' => Carbon::now()->subHours(6 + ($seed % 10))->format('Y-m-d H:i:s'),
                'due_at' => Carbon::now()->addHours(18)->format('Y-m-d H:i:s'),
                'root_cause' => 'Demo seed — pending diagnosis',
            ]),
            'procurement_requests' => array_merge($base, [
                '_title' => $siteLabel.' · PR-'.$n,
                '_status' => 'Approved',
                'name' => 'Materials for '.$siteLabel,
                'discipline' => 'Civil',
                'amount' => 95_000 + ($seed % 9) * 5_000,
                'total_cost' => 95_000 + ($seed % 9) * 5_000,
            ]),
            'purchase_transactions' => array_merge($base, [
                '_title' => $siteLabel.' · PO-'.$n,
                '_status' => 'Received',
                'name' => 'PO '.$siteCode.'-'.$n,
                'total_cost' => 120_000 + ($seed % 6) * 10_000,
                'amount' => 120_000 + ($seed % 6) * 10_000,
                'vendor_id' => $masterIds['vendors'][0] ?? ($masterIds['suppliers'][0] ?? null),
                'supplier_id' => $masterIds['suppliers'][0] ?? ($masterIds['vendors'][0] ?? null),
            ]),
            'construction_projects' => array_merge($base, [
                '_title' => $siteLabel.' · Construction',
                '_status' => 'WIP',
                'project_name' => $siteLabel.' Build',
                'name' => $siteLabel.' Build',
                'project_code' => 'PRJ-'.$siteCode.'-'.$n,
                'budget' => $budget,
                'total_cost' => $actual,
                'project_type' => ($seed % 2 === 0) ? 'GBT' : 'RTT',
            ]),
            'construction_activities' => array_merge($base, [
                '_title' => $siteLabel.' · SKOM',
                'construction_project_id' => $createdParents['construction_projects'] ?? null,
                'activity' => ['SKOM', 'Foundation', 'Tower Erection', 'Electrical'][$seed % 4],
                'start_plan' => Carbon::now()->subDays(30)->toDateString(),
                'start_actual' => Carbon::now()->subDays(28)->toDateString(),
                'finish_plan' => Carbon::now()->addDays(14)->toDateString(),
                'sequence' => $n,
                'activity_remarks' => 'Demo seed activity for '.$siteLabel,
            ]),
            'saq_trackers' => array_merge($base, [
                '_title' => $siteLabel.' · SAQ',
                '_status' => 'In Progress',
                'name' => 'SAQ '.$siteCode,
            ]),
            'power_trackers' => array_merge($base, [
                '_title' => $siteLabel.' · Power',
                '_status' => 'Active',
                'name' => 'Power '.$siteCode,
            ]),
            'postcon_trackers' => array_merge($base, [
                '_title' => $siteLabel.' · Postcon',
                'cfei_milestone' => ['Secured', 'Applied', 'Pending'][$seed % 3],
                'cme_progress' => 35 + ($seed % 60),
                'cfei_applied_date' => Carbon::now()->subDays(40)->toDateString(),
                'cfei_secured_date' => Carbon::now()->subDays(10)->toDateString(),
                'postcon_remarks' => 'Demo postcon tracker for '.$siteLabel,
            ]),
            'site_documents' => array_merge($base, [
                '_title' => $siteLabel.' · Site Documents',
                '_status' => 'Not Required',
                'document_status' => ['Not Required', 'Complete', 'Incomplete'][$seed % 3],
                'document_type' => ['Initial Property Docs', 'Lease Package', 'Permit Pack', 'As-Built'][$seed % 4],
                'lessor_id' => $masterIds['lessors'][0] ?? null,
                'date_secured' => Carbon::now()->subDays(20)->toDateString(),
                'completion_plan_date' => Carbon::now()->addDays(30)->toDateString(),
                'lacking_documents' => 'None — demo seed',
                'document_remarks' => 'Demo site document package for '.$siteLabel,
            ]),
            'payments_and_receipts' => array_merge($base, [
                '_title' => 'SEED-DIS-'.str_pad((string) (100 + $seed), 5, '0', STR_PAD_LEFT),
                '_status' => 'Posted',
                'payment_number' => 'SEED-DIS-'.str_pad((string) (100 + $seed), 5, '0', STR_PAD_LEFT),
                'status' => 'Posted',
                'type' => ($seed % 2 === 0) ? 'Cash Disbursement' : 'Cash Receipt',
                'payment_method' => 'Cash',
                'payment_date' => Carbon::now()->subDays($seed % 25)->toDateString(),
                'amount' => 45_000 + ($seed % 15) * 3_500,
                'supplier_id' => $masterIds['suppliers'][0] ?? ($masterIds['vendors'][0] ?? null),
                'customer_id' => $masterIds['customers'][0] ?? null,
                'account_id' => $masterIds['chart_of_accounts'][2] ?? ($masterIds['chart_of_accounts'][0] ?? null),
                'cost_classification' => 'Capex',
                'cost_category' => 'Capex - Site Acquisition',
                'description' => 'Demo payment attributed to '.$siteLabel,
            ]),
            'land_leases' => array_merge($base, [
                '_title' => $siteLabel.' · Land Lease',
                '_status' => 'Active',
                'name' => 'Lease '.$siteCode,
                'amount' => 45_000 + ($seed % 10) * 1_000,
            ]),
            'site_permits' => array_merge($base, [
                '_title' => $siteLabel.' · Permit',
                '_status' => 'Approved',
                'name' => 'Barangay / Building permit',
            ]),
            'telco_contracts' => array_merge($base, [
                '_title' => $siteLabel.' · Telco Contract',
                '_status' => 'Active',
                'name' => 'Anchor colo '.$siteCode,
            ]),
            'site_colocations' => array_merge($base, [
                '_title' => $siteLabel.' · Colocation',
                'name' => 'Colo '.$siteCode,
            ]),
            default => array_merge($base, ['_title' => $siteLabel.' · '.$slug]),
        };
    }

    /**
     * @param  array<string, list<string>>  $masterIds
     */
    private function seedPurchaseItems(
        ?DynEntity $entity,
        TenantUser $actor,
        string $purchaseId,
        string $parentExternal,
        string $siteLabel,
        int $seed,
        array $masterIds,
        bool $dryRun,
    ): int {
        if (! $entity instanceof DynEntity) {
            return 0;
        }
        $externalId = $parentExternal.':i1';
        if ($this->existsByExternal($entity, $externalId)) {
            return 0;
        }
        if ($dryRun) {
            return 1;
        }
        $qty = 2 + ($seed % 4);
        try {
            $this->createRecord(
                $entity,
                $actor,
                $externalId,
                $siteLabel.' · Line 1',
                'Active',
                [
                    'purchase_transaction_id' => $purchaseId,
                    'product_id' => $masterIds['products'][0] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => 4500,
                    'total_cost' => $qty * 4500,
                    'name' => 'Line item materials',
                ],
                $purchaseId,
            );
        } catch (\Throwable) {
            return 0;
        }

        return 1;
    }

    private function firstRecordId(string $slug): ?string
    {
        $entityId = DynEntity::query()->where('slug', $slug)->value('id');
        if ($entityId === null) {
            return null;
        }

        $id = DynRecord::query()
            ->where('entity_id', $entityId)
            ->where('is_deleted', false)
            ->orderBy('created_at')
            ->value('id');

        return $id !== null ? (string) $id : null;
    }

    private function firstChildForSite(string $slug, string $siteId): ?string
    {
        $entityId = DynEntity::query()->where('slug', $slug)->value('id');
        if ($entityId === null) {
            return null;
        }

        $id = DynRecord::query()
            ->where('entity_id', $entityId)
            ->where('is_deleted', false)
            ->where(function ($q) use ($siteId): void {
                $q->where('parent_record_id', $siteId)
                    ->orWhere('values_json->tower_site_id', $siteId);
            })
            ->orderByDesc('created_at')
            ->value('id');

        return $id !== null ? (string) $id : null;
    }

    private function demoExternalId(string $slug, string $siteId, int $n): string
    {
        // Keep under typical source_external_id varchar(64) — ULIDs collide on 8-char prefixes.
        return self::SOURCE_PREFIX.$slug.':'.substr(sha1($siteId), 0, 12).':'.$n;
    }

    private function seedTicketActivities(
        ?DynEntity $entity,
        TenantUser $actor,
        string $ticketId,
        string $parentExternal,
        string $siteLabel,
        bool $dryRun,
    ): int {
        if (! $entity instanceof DynEntity) {
            return 0;
        }
        $externalId = $parentExternal.':a1';
        if ($this->existsByExternal($entity, $externalId)) {
            return 0;
        }
        if ($dryRun) {
            return 1;
        }
        try {
            $this->createRecord(
                $entity,
                $actor,
                $externalId,
                $siteLabel.' · Activity',
                'Active',
                [
                    'site_ticket_id' => $ticketId,
                    'activity_type' => 'Comment',
                    'notes' => 'Demo seed: ticket acknowledged by NOC.',
                    'activity_at' => now()->format('Y-m-d H:i:s'),
                    'performed_by' => 'NOC L1',
                ],
                $ticketId,
            );
        } catch (\Throwable) {
            return 0;
        }

        return 1;
    }
}
