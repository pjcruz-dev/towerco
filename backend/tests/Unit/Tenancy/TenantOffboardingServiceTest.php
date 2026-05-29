<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantOffboardingService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Tests\TestCase;

final class TenantOffboardingServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'central');
        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('tenancy.database.central_connection', 'central');

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('parent_tenant_id')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
        });

        Schema::connection('central')->create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });
    }

    public function test_delete_tenant_requires_matching_confirmation(): void
    {
        $tenant = $this->createTenantRecord('confirm-test.localhost');

        $this->expectException(ValidationException::class);

        app(TenantOffboardingService::class)->deleteTenant($tenant, [
            'confirmation' => '00000000-0000-0000-0000-000000000000',
        ]);
    }

    public function test_delete_tenant_removes_central_records_and_drops_database(): void
    {
        $tenant = $this->createTenantRecord('delete-test.localhost');

        (new CreateDatabase($tenant))->handle(app(DatabaseManager::class));
        (new MigrateDatabase($tenant))->handle();

        $databaseName = $tenant->database()->getName();
        $this->assertTrue($tenant->database()->manager()->databaseExists($databaseName));

        $result = app(TenantOffboardingService::class)->deleteTenant($tenant, [
            'confirmation' => $tenant->id,
        ]);

        $this->assertSame($tenant->id, $result['tenant_id']);
        $this->assertSame(['delete-test.localhost'], $result['domains_removed']);
        $this->assertTrue($result['database_dropped']);
        $this->assertNull(Tenant::query()->find($tenant->id));
        $this->assertDatabaseMissing('domains', [
            'domain' => 'delete-test.localhost',
        ], 'central');
        $this->assertFalse($tenant->database()->manager()->databaseExists($databaseName));
    }

    public function test_delete_tenant_blocks_when_child_tenants_exist(): void
    {
        $parent = $this->createTenantRecord('parent.localhost');
        Tenant::withoutEvents(function () use ($parent): void {
            Tenant::query()->create([
                'id' => (string) Str::uuid(),
                'parent_tenant_id' => $parent->id,
            ]);
        });

        $this->expectException(ValidationException::class);

        app(TenantOffboardingService::class)->deleteTenant($parent, [
            'confirmation' => $parent->id,
        ]);
    }

    public function test_delete_tenant_cascades_linked_environment_tenants(): void
    {
        $parent = $this->createTenantRecord('parent.localhost');
        $childId = (string) Str::uuid();
        Tenant::withoutEvents(function () use ($parent, $childId): void {
            $child = Tenant::query()->create([
                'id' => $childId,
                'parent_tenant_id' => $parent->id,
            ]);
            $child->domains()->create(['domain' => 'staging.parent.localhost']);
        });

        $result = app(TenantOffboardingService::class)->deleteTenant($parent, [
            'confirmation' => $parent->id,
            'cascade' => true,
        ]);

        $this->assertSame($parent->id, $result['tenant_id']);
        $this->assertContains($childId, $result['children_deleted']);
        $this->assertNull(Tenant::query()->find($parent->id));
        $this->assertNull(Tenant::query()->find($childId));
    }

    private function createTenantRecord(string $domain): Tenant
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutEvents(function () use ($domain): Tenant {
            $record = Tenant::query()->create(['id' => (string) Str::uuid()]);
            $record->domains()->create(['domain' => $domain]);

            return $record->fresh(['domains']);
        });

        return $tenant;
    }
}
