<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantAdminBootstrapService;
use App\Modules\Tenancy\Services\TenantEnvironmentProvisioningService;
use App\Modules\Tenancy\Services\TenantModuleRbacSyncService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Stancl\Tenancy\Events as TenancyEvents;
use Tests\TestCase;

final class TenantEnvironmentProvisioningServiceTest extends TestCase
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
        Config::set('cache.default', 'array');
        Config::set('queue.default', 'sync');

        Event::fake([
            TenancyEvents\TenantCreated::class,
            TenancyEvents\DomainCreated::class,
        ]);

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('slug', 64)->nullable();
            $table->string('brand_domain', 255)->nullable();
            $table->string('environment', 32)->default('production');
            $table->string('tco_sequence_prefix', 8)->nullable();
            $table->string('parent_tenant_id')->nullable();
            $table->boolean('mfa_required')->default(true);
            $table->string('plan_tier', 32)->default('starter');
            $table->string('subscription_status', 32)->default('active');
            $table->unsignedInteger('seat_limit')->default(25);
            $table->json('enabled_modules')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
            $table->unique(['slug', 'environment'], 'tenants_slug_environment_unique');
        });

        Schema::connection('central')->create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
        });

        Schema::connection('central')->create('tenant_domain_endpoints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('purpose', 32);
            $table->string('hostname', 255);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'purpose']);
            $table->unique('hostname');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        $this->mock(TenantAdminBootstrapService::class, function ($mock): void {
            $mock->shouldReceive('bootstrap')->andReturn([
                'email' => 'admin@example.test',
                'password' => 'generated-password-12',
                'password_generated' => true,
            ]);
        });

        $this->mock(TenantModuleRbacSyncService::class, function ($mock): void {
            $mock->shouldReceive('syncForTenant')->byDefault();
        });
    }

    public function test_creates_linked_staging_tenant_from_production_source(): void
    {
        $source = $this->createSourceTenant('production', 'atc', 'alliancetowers.com', 'app.alliancetowers.com');

        $result = app(TenantEnvironmentProvisioningService::class)->createFromTenant($source, [
            'environment' => 'staging',
            'migrate' => false,
        ]);

        $created = $result['tenant'];
        $this->assertSame('staging', $created->environment);
        $this->assertSame('atc', $created->slug);
        $this->assertSame($source->id, $created->parent_tenant_id);
        $this->assertSame('staging.alliancetowers.com', $created->domains()->first()?->domain);
        $this->assertSame($source->id, $result['org_root_tenant_id']);
        $this->assertArrayHasKey('initial_admin', $result);
    }

    public function test_blocks_duplicate_environment_for_same_slug(): void
    {
        $source = $this->createSourceTenant('production', 'atc', 'alliancetowers.com', 'app.alliancetowers.com');

        app(TenantEnvironmentProvisioningService::class)->createFromTenant($source, [
            'environment' => 'staging',
            'migrate' => false,
        ]);

        $this->expectException(ValidationException::class);

        app(TenantEnvironmentProvisioningService::class)->createFromTenant($source, [
            'environment' => 'staging',
            'migrate' => false,
        ]);
    }

    private function createSourceTenant(
        string $environment,
        string $slug,
        string $brandDomain,
        string $domain,
    ): Tenant {
        /** @var Tenant $tenant */
        $tenant = Tenant::withoutEvents(function () use ($environment, $slug, $brandDomain, $domain): Tenant {
            $record = Tenant::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'brand_domain' => $brandDomain,
                'environment' => $environment,
            ]);
            $record->domains()->create(['domain' => $domain]);

            return $record->fresh(['domains']);
        });

        return $tenant;
    }
}
