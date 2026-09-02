<?php

declare(strict_types=1);

namespace Tests\Unit\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynEntityAdminService;
use App\Modules\DynamicEntities\Services\DynRecordImportService;
use App\Modules\Identity\Models\TenantUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class DynRecordImportValidationTest extends TestCase
{
    private TenantUser $actor;

    protected function setUp(): void
    {
        parent::setUp();

        $sqlite = [
            'driver' => 'sqlite',
            'database' => 'file:dyn_import_'.uniqid('', true).'?mode=memory&cache=shared',
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
    public function dry_run_reports_type_and_required_errors_without_writing(): void
    {
        $admin = app(DynEntityAdminService::class);
        $import = app(DynRecordImportService::class);

        $entity = $admin->createEntity([
            'name' => 'Bank Accounts',
            'slug' => 'bank_accounts',
            'module_pack' => 'finance',
        ], $this->actor);

        $admin->createField($entity, [
            'label' => 'Account Name',
            'name' => 'account_name',
            'type' => 'text',
            'is_required' => true,
            'show_in_table' => true,
        ]);
        $admin->createField($entity, [
            'label' => 'Current Balance',
            'name' => 'current_balance',
            'type' => 'decimal',
            'is_required' => false,
            'show_in_table' => true,
        ]);
        $admin->createField($entity, [
            'label' => 'Status',
            'name' => 'acct_status',
            'type' => 'select',
            'is_required' => false,
            'show_in_table' => true,
            'options_json' => ['Active', 'Closed'],
        ]);

        $csv = "account_name,current_balance,acct_status\n".
            ",100,Active\n".
            "Savings,not-a-number,Active\n".
            "Checking,250.50,Paused\n".
            "Payroll,1000,Active\n";

        $file = UploadedFile::fake()->createWithContent('bank.csv', $csv);

        $preview = $import->import(
            $entity->fresh(['fields']),
            $file,
            [
                'account_name' => 'account_name',
                'current_balance' => 'current_balance',
                'acct_status' => 'acct_status',
            ],
            null,
            $this->actor,
            true,
        );

        $this->assertTrue($preview['dry_run']);
        $this->assertSame(1, $preview['would_create']);
        $this->assertSame(0, $preview['created']);
        $this->assertGreaterThanOrEqual(3, $preview['error_rows']);
        $this->assertSame(0, DB::table('dyn_records')->where('entity_id', $entity->id)->count());

        $messages = collect($preview['errors'])->pluck('message')->implode(' | ');
        $this->assertStringContainsString('required', strtolower($messages));
        $this->assertStringContainsString('number', strtolower($messages));
        $this->assertStringContainsString('one of', strtolower($messages));
    }

    #[Test]
    public function import_writes_only_valid_rows_after_stricter_validation(): void
    {
        $admin = app(DynEntityAdminService::class);
        $import = app(DynRecordImportService::class);

        $entity = $admin->createEntity([
            'name' => 'Bank Accounts',
            'slug' => 'bank_accounts_write',
            'module_pack' => 'finance',
        ], $this->actor);

        $admin->createField($entity, [
            'label' => 'Account Name',
            'name' => 'account_name',
            'type' => 'text',
            'is_required' => true,
            'show_in_table' => true,
        ]);
        $admin->createField($entity, [
            'label' => 'Current Balance',
            'name' => 'current_balance',
            'type' => 'decimal',
            'show_in_table' => true,
        ]);

        $csv = "account_name,current_balance\nPayroll,1000\nBad,abc\n";
        $file = UploadedFile::fake()->createWithContent('bank-write.csv', $csv);

        $result = $import->import(
            $entity->fresh(['fields']),
            $file,
            [
                'account_name' => 'account_name',
                'current_balance' => 'current_balance',
            ],
            null,
            $this->actor,
            false,
        );

        $this->assertFalse($result['dry_run']);
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, $result['error_rows']);
        $this->assertDatabaseHas('dyn_records', [
            'entity_id' => $entity->id,
            'title' => 'Payroll',
        ]);
    }

    #[Test]
    public function metacoresoft_export_labels_auto_map_and_template_uses_labels(): void
    {
        $admin = app(DynEntityAdminService::class);
        $import = app(DynRecordImportService::class);

        $entity = $admin->createEntity([
            'name' => 'Bank Accounts',
            'slug' => 'bank_accounts',
            'module_pack' => 'finance',
        ], $this->actor);

        $admin->createField($entity, [
            'label' => 'Account Number',
            'name' => 'account_number',
            'type' => 'text',
            'show_in_table' => true,
        ]);
        $admin->createField($entity, [
            'label' => 'Bank Name',
            'name' => 'bank_name',
            'type' => 'text',
            'show_in_table' => true,
        ]);
        $admin->createField($entity, [
            'label' => 'Account Name',
            'name' => 'account_name',
            'type' => 'text',
            'is_required' => true,
            'show_in_table' => true,
        ]);
        $admin->createField($entity, [
            'label' => 'Current Balance',
            'name' => 'current_balance',
            'type' => 'decimal',
            'show_in_table' => true,
        ]);

        $fresh = $entity->fresh(['fields']);
        $template = $import->buildTemplate($fresh);
        $this->assertContains('Account Number', $template['headers']);
        $this->assertContains('Account Name', $template['headers']);
        $this->assertContains('Current Balance', $template['headers']);

        $bomCsv = "\xEF\xBB\xBF".'"Account Number","Bank Name","Account Name",Status,"Current Balance"'."\n".
            'ACC-1,BDO,Ops Account,Active,1500.25'."\n";
        $file = UploadedFile::fake()->createWithContent('bank_accounts_template.csv', $bomCsv);

        $analyze = $import->analyze($fresh, $file);
        $this->assertSame('account_number', $analyze['suggested_map']['Account Number'] ?? null);
        $this->assertSame('bank_name', $analyze['suggested_map']['Bank Name'] ?? null);
        $this->assertSame('account_name', $analyze['suggested_map']['Account Name'] ?? null);
        $this->assertSame('status', $analyze['suggested_map']['Status'] ?? null);
        $this->assertSame('current_balance', $analyze['suggested_map']['Current Balance'] ?? null);

        $this->assertSame(
            'products',
            \App\Modules\DynamicEntities\Support\AtcMetacoresoftExportMap::resolveSlug('materials_&_equipment'),
        );
        $this->assertGreaterThanOrEqual(37, count(\App\Modules\DynamicEntities\Support\AtcMetacoresoftExportMap::entitySlugs()));
    }
}
