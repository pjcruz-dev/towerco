<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Listeners\ScopeTenantPermissionCache;
use App\Modules\Tenancy\Services\TenantRbacBaselineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Database\DatabaseManager;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Tests\TestCase;

final class ScopeTenantPermissionCacheTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'cache.default' => 'array',
            'permission.cache.store' => 'array',
            'database.default' => 'central',
            'database.connections.central' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);

        DB::purge('central');
        DB::setDefaultConnection('central');

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->timestamps();
            $table->json('data')->nullable();
            $table->boolean('mfa_required')->default(false);
            $table->string('plan_tier', 32)->default('professional');
            $table->string('subscription_status', 32)->default('active');
            $table->unsignedInteger('seat_limit')->default(25);
        });

        Schema::connection('central')->create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
        });

        Tenant::withoutEvents(function (): void {
            $this->tenant = Tenant::query()->create(['id' => (string) Str::uuid()]);
            $this->tenant->domains()->create(['domain' => 'tenant-a.localhost']);
        });

        $manager = app(DatabaseManager::class);
        (new CreateDatabase($this->tenant))->handle($manager);
        (new MigrateDatabase($this->tenant))->handle();
    }

    public function test_tenancy_bootstrap_scopes_spatie_permission_cache_key(): void
    {
        $registrar = app(PermissionRegistrar::class);

        tenancy()->initialize($this->tenant);

        $this->assertSame(
            ScopeTenantPermissionCache::BASE_CACHE_KEY.'.tenant.'.$this->tenant->id,
            $registrar->cacheKey,
        );

        tenancy()->end();

        $this->assertSame(
            ScopeTenantPermissionCache::BASE_CACHE_KEY,
            $registrar->cacheKey,
        );
    }

    public function test_tenant_admin_permission_checks_work_under_scoped_cache(): void
    {
        tenancy()->initialize($this->tenant);
        app(TenantRbacBaselineService::class)->ensure();

        $admin = TenantUser::query()->create([
            'name' => 'Admin',
            'email' => 'admin@tenant-a.localhost',
            'password' => 'password',
            'is_active' => true,
        ]);
        $admin->assignRole('tenant_admin');

        $this->assertTrue($admin->hasPermissionTo('user:manage'));
        $this->assertTrue($admin->can('project_one:view'));
        $this->assertTrue($admin->can('e_approval:forms:manage'));

        tenancy()->end();
    }
}
