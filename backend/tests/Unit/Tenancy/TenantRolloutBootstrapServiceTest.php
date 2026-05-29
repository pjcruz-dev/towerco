<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantRolloutBootstrapService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Tests\TestCase;

final class TenantRolloutBootstrapServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.connections.central', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        Config::set('toweros.tenant_provisioning.auto_seed_holidays', true);
        Config::set('toweros.tenant_provisioning.seed_next_holiday_year', false);

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
    }

    public function test_provision_seeds_philippines_holidays_for_current_year(): void
    {
        Tenant::withoutEvents(function (): void {
            $this->tenant = Tenant::query()->create(['id' => (string) Str::uuid()]);
            $this->tenant->domains()->create(['domain' => 'bootstrap-test.localhost']);
        });

        (new CreateDatabase($this->tenant))->handle(app(DatabaseManager::class));
        (new MigrateDatabase($this->tenant))->handle();

        $result = app(TenantRolloutBootstrapService::class)->provision($this->tenant);

        $this->assertGreaterThan(10, $result['public_holidays_seeded']);
        $this->assertSame([(int) now()->format('Y')], $result['holiday_years']);
    }

    private Tenant $tenant;
}
