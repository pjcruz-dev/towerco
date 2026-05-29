<?php

declare(strict_types=1);

namespace Tests\Support\Concerns;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;

trait InteractsWithInMemoryTenantApi
{
    protected Tenant $testTenant;

    protected TenantUser $testTenantAdmin;

    protected function bootInMemoryTenantApi(): void
    {
        config([
            'database.connections.central' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'toweros.allow_tenant_on_central_host' => true,
        ]);

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->timestamps();
            $table->json('data')->nullable();
        });

        Schema::connection('central')->create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
        });

        Tenant::withoutEvents(function (): void {
            $this->testTenant = Tenant::query()->create(['id' => (string) Str::uuid()]);
            $this->testTenant->domains()->create(['domain' => 'test.localhost']);
        });

        (new CreateDatabase($this->testTenant))->handle(app(DatabaseManager::class));
        (new MigrateDatabase($this->testTenant))->handle();

        tenancy()->initialize($this->testTenant);
        $this->ensurePublicHolidayTable();
        app(TenantRbacBaselineService::class)->ensure();
        $this->testTenantAdmin = TenantUser::query()->create([
            'name' => 'Test Admin',
            'email' => 'admin@test.localhost',
            'password' => 'password',
        ]);
        $this->testTenantAdmin->assignRole('tenant_admin');
        tenancy()->end();
    }

    /**
     * @return array<string, string>
     */
    protected function tenantApiHeaders(): array
    {
        return [
            'X-Tenant-Id' => $this->testTenant->id,
        ];
    }

    protected function actingAsTenantAdmin(): static
    {
        return $this->actingAs($this->testTenantAdmin, 'sanctum');
    }

    private function ensurePublicHolidayTable(): void
    {
        if (Schema::connection('tenant')->hasTable('tenant_public_holidays')) {
            return;
        }

        Schema::connection('tenant')->create('tenant_public_holidays', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->date('holiday_date');
            $table->string('name');
            $table->string('region', 64)->nullable();
            $table->unsignedSmallInteger('calendar_year');
            $table->timestamps();
            $table->unique(['holiday_date', 'region']);
        });
    }
}
