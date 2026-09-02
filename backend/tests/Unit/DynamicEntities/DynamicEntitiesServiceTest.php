<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Models\DynEntity;
use App\Modules\DynamicEntities\Services\AtcMetaImportService;
use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Services\DynRecordService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DynamicEntitiesServiceTest extends TestCase
{
    private TenantUser $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = [
            'driver' => 'sqlite',
            'database' => 'file:dyn_svc_'.uniqid('', true).'?mode=memory&cache=shared',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ];
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite' => $sqlite,
            'database.connections.tenant' => $sqlite,
            'toweros.logging.audit_structured_enabled' => false,
        ]);
        DB::purge('sqlite');
        DB::purge('tenant');
        DB::setDefaultConnection('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('tenant_activity_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('module', 50);
            $table->string('action', 80);
            $table->string('category', 32)->nullable();
            $table->string('severity', 16)->nullable();
            $table->string('summary')->nullable();
            $table->string('reason')->nullable();
            $table->string('entity_type', 50)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('entity_label')->nullable();
            $table->uuid('actor_user_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        $migration = require database_path('migrations/tenant/2026_08_29_140000_create_dynamic_entities_tables.php');
        $migration->up();
        foreach ([
            '2026_08_29_170000_add_dyn_field_group_layout_options.php',
            '2026_08_29_180000_add_dyn_entity_print_settings.php',
            '2026_08_29_190000_add_dyn_field_key_and_totals.php',
        ] as $file) {
            $step = require database_path('migrations/tenant/'.$file);
            $step->up();
        }

        $this->actor = new TenantUser([
            'id' => (string) Str::uuid(),
            'name' => 'Actor',
            'email' => 'actor@test.local',
        ]);
        $this->actor->id = (string) Str::uuid();
        DB::table('users')->insert([
            'id' => $this->actor->id,
            'name' => 'Actor',
            'email' => 'actor@test.local',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    #[Test]
    public function creates_entity_field_and_record_with_title_from_values(): void
    {
        $admin = app(DynEntityAdminService::class);
        $records = app(DynRecordService::class);

        $entity = $admin->createEntity([
            'name' => 'Tower Sites',
            'slug' => 'tower_sites',
            'module_pack' => 'pm',
        ], $this->actor);

        $admin->createField($entity, [
            'label' => 'Site Name',
            'name' => 'site_name',
            'type' => 'text',
            'is_required' => true,
            'show_in_table' => true,
            'is_filterable' => true,
        ]);

        $entity->refresh();
        $record = $records->create($entity, [
            'values' => ['site_name' => 'Site Alpha'],
            'status' => 'WIP',
        ], $this->actor);

        $this->assertSame('Site Alpha', $record->title);
        $this->assertSame('WIP', $record->status);
        $this->assertDatabaseHas('dyn_record_indexes', [
            'record_id' => $record->id,
            'field_name' => 'site_name',
            'value_string' => 'Site Alpha',
        ]);
    }

    #[Test]
    public function imports_metacore_entity_and_field_rows_from_dump_snippet(): void
    {
        $path = storage_path('framework/testing/atc-meta-snippet.sql');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, <<<'SQL'
INSERT INTO `entities` (`id`, `name`, `description`, `storage_mode`, `is_location_based`, `slug`, `linked_table`, `created_by_user_id`) VALUES('22', 'Tower Sites', 'Tower Sites and Portfolio', 'physical', '0', 'tower_sites', NULL, '2');
INSERT INTO `fields` (`id`, `entity_id`, `name`, `type`, `is_required`, `is_priority`, `is_system_field`, `system_column`, `show_in_table`, `options`, `target_entity_id`, `label`, `placeholder`, `attributes`, `formula_definition`, `identifier_prefix`, `identifier_format`, `process_button_config`, `created_at`, `updated_at`, `created_by_user_id`, `column_span`, `field_order`, `row_id`, `view_row_id`, `view_order_id`, `view_column_span`, `is_filterable`, `calculate_totals`, `is_virtual`, `is_pinned`, `form_group_id`, `view_group_id`, `conditional_rules`) VALUES('100', '22', 'site_name', 'text', '1', '0', '0', 'site_name', '1', NULL, NULL, 'Site Name', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '6', '10', NULL, NULL, '0', '6', '1', '0', '0', '0', NULL, NULL, NULL);
SQL);

        $stats = app(AtcMetaImportService::class)->importFromDump($path);

        $this->assertSame(1, $stats['entities']);
        $this->assertSame(1, $stats['fields']);
        $this->assertTrue(DynEntity::query()->where('slug', 'tower_sites')->exists());
        $entity = DynEntity::query()->where('slug', 'tower_sites')->firstOrFail();
        $this->assertSame('pm', $entity->module_pack);
        $this->assertTrue($entity->fields()->where('name', 'site_name')->exists());
        $this->assertNotEmpty($entity->related_tabs_json);

        @unlink($path);
    }

    #[Test]
    public function imports_dat_rows_into_dyn_records_for_phase1(): void
    {
        $admin = app(DynEntityAdminService::class);
        $entity = $admin->createEntity([
            'name' => 'Tower Sites',
            'slug' => 'tower_sites',
            'module_pack' => 'pm',
            'source_linked_table' => 'dat_tower_sites',
        ], $this->actor);
        $admin->createField($entity, [
            'label' => 'Site Name',
            'name' => 'site_name',
            'type' => 'text',
            'show_in_table' => true,
            'system_column' => 'site_name',
        ]);
        $admin->createField($entity, [
            'label' => 'Status',
            'name' => 'status',
            'type' => 'select',
            'show_in_table' => true,
            'system_column' => 'status',
        ]);

        $path = storage_path('framework/testing/atc-data-snippet.sql');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, <<<'SQL'
INSERT INTO `dat_tower_sites` (`id`, `site_name`, `status`, `is_deleted`, `created_at`) VALUES('706', 'Brgy Catarman Liloan', 'WIP', '0', '2026-01-01 00:00:00');
INSERT INTO `dat_tower_sites` (`id`, `site_name`, `status`, `is_deleted`, `created_at`) VALUES('705', 'Deleted Site', 'WIP', '1', '2026-01-01 00:00:00');
SQL);

        $stats = app(\App\Modules\DynamicEntities\Services\AtcDataImportService::class)
            ->importFromDump($path, ['tower_sites']);

        $this->assertSame(1, $stats['entities']);
        $this->assertSame(1, $stats['records']);
        $this->assertSame(1, $stats['skipped']);
        $this->assertDatabaseHas('dyn_records', [
            'entity_id' => $entity->id,
            'source_external_id' => '706',
            'title' => 'Brgy Catarman Liloan',
            'status' => 'WIP',
        ]);
        $entity->refresh();
        $this->assertNotEmpty($entity->related_tabs_json);

        @unlink($path);
    }

    #[Test]
    public function purchase_monitoring_aggregates_goods_receipts(): void
    {
        $admin = app(DynEntityAdminService::class);

        $suppliers = $admin->createEntity([
            'name' => 'Suppliers',
            'slug' => 'suppliers',
            'module_pack' => 'procurement',
        ], $this->actor);
        $admin->createField($suppliers, [
            'label' => 'Name',
            'name' => 'name',
            'type' => 'text',
            'show_in_table' => true,
        ]);
        $supplier = app(DynRecordService::class)->create($suppliers, [
            'values' => ['name' => 'Cummins Power'],
            'status' => 'Active',
        ], $this->actor);

        $purchases = $admin->createEntity([
            'name' => 'Purchase Transactions',
            'slug' => 'purchase_transactions',
            'module_pack' => 'procurement',
        ], $this->actor);
        foreach (['purchase_number', 'purchase_date', 'transaction_type', 'supplier_id', 'total_amount'] as $name) {
            $admin->createField($purchases, [
                'label' => $name,
                'name' => $name,
                'type' => $name === 'total_amount' ? 'number' : 'text',
                'show_in_table' => true,
            ]);
        }

        app(DynRecordService::class)->create($purchases, [
            'title' => 'SEED-GR-0001',
            'status' => 'Posted',
            'values' => [
                'purchase_number' => 'SEED-GR-0001',
                'purchase_date' => '2026-08-01',
                'transaction_type' => 'Goods Receipt',
                'supplier_id' => $supplier->id,
                'total_amount' => 1500000,
            ],
        ], $this->actor);

        $report = app(\App\Modules\DynamicEntities\Services\PurchaseMonitoringService::class)->build([
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]);

        $this->assertSame(1500000.0, $report['kpis']['total_purchases']);
        $this->assertSame(1, $report['kpis']['total_transactions']);
        $this->assertSame(1, $report['kpis']['goods_received_count']);
        $this->assertSame(1, $report['kpis']['active_suppliers']);
        $this->assertSame('Cummins Power', $report['by_supplier'][0]['supplier_name']);
    }

    #[Test]
    public function materials_issued_report_counts_issue_movements(): void
    {
        $admin = app(DynEntityAdminService::class);
        $sites = $admin->createEntity([
            'name' => 'Tower Sites',
            'slug' => 'tower_sites',
            'module_pack' => 'pm',
        ], $this->actor);
        $admin->createField($sites, ['label' => 'Site Name', 'name' => 'site_name', 'type' => 'text']);
        $admin->createField($sites, ['label' => 'Region', 'name' => 'region', 'type' => 'text']);
        $site = app(DynRecordService::class)->create($sites, [
            'values' => ['site_name' => 'Site A', 'region' => 'NCR'],
        ], $this->actor);

        $movements = $admin->createEntity([
            'name' => 'Stock Movements',
            'slug' => 'stock_movements',
            'module_pack' => 'procurement',
        ], $this->actor);
        foreach (['movement_date', 'movement_type', 'tower_site_id', 'total_cost', 'quantity'] as $name) {
            $admin->createField($movements, [
                'label' => $name,
                'name' => $name,
                'type' => in_array($name, ['total_cost', 'quantity'], true) ? 'number' : 'text',
            ]);
        }
        app(DynRecordService::class)->create($movements, [
            'values' => [
                'movement_date' => '2026-06-01',
                'movement_type' => 'Issue to Site',
                'tower_site_id' => $site->id,
                'total_cost' => 100000,
                'quantity' => 2,
            ],
        ], $this->actor);

        $report = app(\App\Modules\DynamicEntities\Services\MaterialsIssuedReportService::class)->build([
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]);

        $this->assertSame(1, $report['kpis']['towers_supplied']);
        $this->assertSame(100000.0, $report['kpis']['issued']);
        $this->assertSame(100000.0, $report['kpis']['net_material_cost']);
    }

    #[Test]
    public function executive_dashboard_aggregates_site_portfolio_kpis(): void
    {
        $admin = app(DynEntityAdminService::class);
        $sites = $admin->createEntity([
            'name' => 'Tower Sites',
            'slug' => 'tower_sites',
            'module_pack' => 'pm',
        ], $this->actor);
        foreach (['site_name', 'region', 'project_type', 'milestone', 'days_to_rfti'] as $name) {
            $admin->createField($sites, [
                'label' => $name,
                'name' => $name,
                'type' => $name === 'days_to_rfti' ? 'number' : 'text',
            ]);
        }

        app(DynRecordService::class)->create($sites, [
            'status' => 'WIP',
            'values' => [
                'site_name' => 'Site North',
                'region' => 'NCR',
                'project_type' => 'GBT',
                'milestone' => 'Site Acquisition',
                'days_to_rfti' => -5,
            ],
        ], $this->actor);
        app(DynRecordService::class)->create($sites, [
            'status' => 'RFTI Ready',
            'values' => [
                'site_name' => 'Site South',
                'region' => 'Visayas',
                'project_type' => 'RTP',
                'milestone' => 'RFTI',
                'days_to_rfti' => 45,
            ],
        ], $this->actor);

        $dashboard = app(\App\Modules\DynamicEntities\Services\AtcExecutiveDashboardService::class)->build();

        $this->assertNull($dashboard['message']);
        $this->assertSame(2, $dashboard['kpis']['sites_total']);
        $this->assertGreaterThanOrEqual(1, $dashboard['kpis']['sites_wip']);
        $this->assertGreaterThanOrEqual(1, $dashboard['kpis']['sites_rfti_ready']);
        $this->assertSame(1, $dashboard['rfti_pipeline']['aging'][2]['value']); // overdue
        $this->assertNotEmpty($dashboard['portfolio']['by_region']);
        $this->assertNotEmpty($dashboard['quick_links']);
        $this->assertNotEmpty($dashboard['attention']);
    }

    #[Test]
    public function builds_extended_cas_and_stock_reports(): void
    {
        $admin = app(DynEntityAdminService::class);
        $records = app(DynRecordService::class);

        $products = $admin->createEntity(['name' => 'Products', 'slug' => 'products', 'module_pack' => 'procurement'], $this->actor);
        $admin->createField($products, ['label' => 'Name', 'name' => 'product_name', 'type' => 'text']);
        $product = $records->create($products, ['values' => ['product_name' => 'Bolt Kit']], $this->actor);

        $warehouses = $admin->createEntity(['name' => 'Warehouses', 'slug' => 'warehouses', 'module_pack' => 'procurement'], $this->actor);
        $admin->createField($warehouses, ['label' => 'Name', 'name' => 'warehouse_name', 'type' => 'text']);
        $warehouse = $records->create($warehouses, ['values' => ['warehouse_name' => 'Main WH']], $this->actor);

        $movements = $admin->createEntity(['name' => 'Stock Movements', 'slug' => 'stock_movements', 'module_pack' => 'procurement'], $this->actor);
        foreach (['movement_type', 'quantity', 'total_cost', 'product_id', 'warehouse_id'] as $field) {
            $admin->createField($movements, ['label' => $field, 'name' => $field, 'type' => 'text']);
        }
        $records->create($movements, [
            'values' => [
                'movement_type' => 'receipt',
                'quantity' => 10,
                'total_cost' => 1000,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ],
        ], $this->actor);
        $records->create($movements, [
            'values' => [
                'movement_type' => 'issue',
                'quantity' => 3,
                'total_cost' => 300,
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
            ],
        ], $this->actor);

        $sales = $admin->createEntity(['name' => 'Sales', 'slug' => 'sales_transactions', 'module_pack' => 'finance'], $this->actor);
        $admin->createField($sales, ['label' => 'Amount', 'name' => 'total_amount', 'type' => 'number']);
        $admin->createField($sales, ['label' => 'Date', 'name' => 'transaction_date', 'type' => 'date']);
        $records->create($sales, [
            'status' => 'Open',
            'values' => [
                'total_amount' => 5000,
                'transaction_date' => now()->toDateString(),
            ],
        ], $this->actor);

        $service = app(\App\Modules\DynamicEntities\Services\AtcExtendedReportsService::class);
        $stock = $service->build('stock-on-hand');
        $this->assertNull($stock['message']);
        $this->assertSame(1, $stock['kpis'][0]['value']);
        $this->assertSame(7.0, $stock['kpis'][1]['value']);

        $cas = $service->build('cas-executive-dashboard');
        $this->assertGreaterThan(0, $cas['kpis'][0]['value']);
        $this->assertNotEmpty($cas['charts']);
    }

    #[Test]
    public function seeds_ticketing_pack_and_builds_ops_board(): void
    {
        $admin = app(DynEntityAdminService::class);
        $sites = $admin->createEntity([
            'name' => 'Tower Sites',
            'slug' => 'tower_sites',
            'module_pack' => 'pm',
        ], $this->actor);
        $admin->createField($sites, ['label' => 'Site Name', 'name' => 'site_name', 'type' => 'text']);
        $site = app(DynRecordService::class)->create($sites, [
            'values' => ['site_name' => 'Site Alpha'],
            'status' => 'WIP',
        ], $this->actor);

        $stats = app(\App\Modules\DynamicEntities\Services\AtcTicketingPackSeedService::class)
            ->seed($this->actor->id);

        $this->assertSame(3, $stats['entities']);
        $this->assertGreaterThan(10, $stats['fields']);
        $this->assertSame(4, $stats['categories']);
        $this->assertTrue($stats['related_tabs']);
        $this->assertTrue(DynEntity::query()->where('slug', 'site_tickets')->where('module_pack', 'ticketing')->exists());

        $tickets = DynEntity::query()->where('slug', 'site_tickets')->firstOrFail();
        app(DynRecordService::class)->create($tickets, [
            'title' => 'Genset fault',
            'status' => 'Open',
            'values' => [
                'ticket_number' => 'ST-1001',
                'subject' => 'Genset fault',
                'priority' => 'Critical',
                'ticket_type' => 'Incident',
                'status' => 'Open',
                'tower_site_id' => $site->id,
                'due_at' => '2020-01-01',
                'assigned_to' => 'Field Tech',
            ],
        ], $this->actor);

        $board = app(\App\Modules\DynamicEntities\Services\AtcTicketingBoardService::class)->build();

        $this->assertNull($board['message']);
        $this->assertSame(1, $board['kpis']['total']);
        $this->assertSame(1, $board['kpis']['open']);
        $this->assertSame(1, $board['kpis']['critical_open']);
        $this->assertSame(1, $board['kpis']['overdue']);
        $this->assertSame('ST-1001', $board['recent'][0]['ticket_number']);
    }

    #[Test]
    public function verify_import_reconciles_dump_eligible_vs_dyn_records(): void
    {
        $admin = app(DynEntityAdminService::class);
        $entity = $admin->createEntity([
            'name' => 'Tower Sites',
            'slug' => 'tower_sites',
            'module_pack' => 'pm',
            'source_linked_table' => 'dat_tower_sites',
        ], $this->actor);
        $admin->createField($entity, [
            'label' => 'Site Name',
            'name' => 'site_name',
            'type' => 'text',
            'system_column' => 'site_name',
        ]);

        $path = storage_path('framework/testing/atc-verify-snippet.sql');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, <<<'SQL'
INSERT INTO `dat_tower_sites` (`id`, `site_name`, `status`, `is_deleted`, `created_at`) VALUES('801', 'Verify Site A', 'WIP', '0', '2026-01-01 00:00:00');
INSERT INTO `dat_tower_sites` (`id`, `site_name`, `status`, `is_deleted`, `created_at`) VALUES('802', 'Verify Site B', 'WIP', '0', '2026-01-01 00:00:00');
INSERT INTO `dat_tower_sites` (`id`, `site_name`, `status`, `is_deleted`, `created_at`) VALUES('803', 'Deleted', 'WIP', '1', '2026-01-01 00:00:00');
SQL);

        $preview = app(\App\Modules\DynamicEntities\Services\AtcImportVerifyService::class)
            ->previewImport($path, ['tower_sites'], 'phase1');
        $this->assertSame(1, $preview['entities']);
        $this->assertSame(2, $preview['would_import']);
        $this->assertSame(1, $preview['would_skip']);

        app(\App\Modules\DynamicEntities\Services\AtcDataImportService::class)
            ->importFromDump($path, ['tower_sites']);

        $result = app(\App\Modules\DynamicEntities\Services\AtcImportVerifyService::class)
            ->verify($path, ['tower_sites'], 'phase1', 0, null);

        $this->assertTrue($result['ok']);
        $this->assertSame('ok', $result['rows'][0]['status']);
        $this->assertSame(2, $result['rows'][0]['dump_eligible']);
        $this->assertSame(2, $result['rows'][0]['dyn_imported']);
        $this->assertSame(0, $result['rows'][0]['delta']);

        @unlink($path);
    }
}
