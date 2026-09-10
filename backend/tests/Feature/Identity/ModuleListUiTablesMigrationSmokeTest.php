<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Smoke: prefs / shared layouts / exports tenant migrations apply cleanly.
 * Runs the migration classes directly on an in-memory SQLite connection so the
 * check does not depend on Redis/Docker tenancy migrate plumbing.
 */
final class ModuleListUiTablesMigrationSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
    }

    public function test_prefs_shared_layouts_and_exports_migrations_create_tables(): void
    {
        $prefsPath = database_path('migrations/tenant/2026_09_10_150000_create_user_ui_preferences_table.php');
        $layoutsPath = database_path('migrations/tenant/2026_09_10_160000_create_module_list_shared_layouts_and_exports.php');

        $this->assertFileExists($prefsPath);
        $this->assertFileExists($layoutsPath);

        // FK targets used by the migrations.
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->timestamps();
        });

        $prefsMigration = require $prefsPath;
        $prefsMigration->up();

        $layoutsMigration = require $layoutsPath;
        $layoutsMigration->up();

        $this->assertTrue(Schema::hasTable('user_ui_preferences'));
        $this->assertTrue(Schema::hasTable('module_list_shared_layouts'));
        $this->assertTrue(Schema::hasTable('module_list_exports'));

        $this->assertTrue(Schema::hasColumns('user_ui_preferences', [
            'id',
            'user_id',
            'preference_key',
            'value_json',
        ]));
        $this->assertTrue(Schema::hasColumns('module_list_shared_layouts', [
            'id',
            'storage_key',
            'layouts_json',
            'updated_by',
        ]));
        $this->assertTrue(Schema::hasColumns('module_list_exports', [
            'id',
            'user_id',
            'module',
            'filename',
            'format',
            'status',
            'filters_json',
        ]));
    }
}
