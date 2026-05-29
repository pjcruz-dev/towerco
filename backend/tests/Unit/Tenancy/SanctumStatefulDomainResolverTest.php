<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\TenantDomainEndpoint;
use App\Modules\Tenancy\Support\SanctumStatefulDomainResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Stancl\Tenancy\Database\Models\Domain;
use Tests\TestCase;

final class SanctumStatefulDomainResolverTest extends TestCase
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
        Config::set('toweros.sanctum.stateful_domain_cache_ttl', 0);
        Config::set('toweros.tenant_app_url', 'http://localhost:3001');
        Config::set('toweros.sanctum.stateful_domain_extras', 'platform.localhost');

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

        Schema::connection('central')->create('tenant_domain_endpoints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('purpose', 32);
            $table->string('hostname', 255);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'purpose']);
            $table->unique('hostname');
        });
    }

    public function test_it_merges_tenant_domains_with_dev_port_variants_and_extras(): void
    {
        $tenantId = (string) Str::uuid();

        Domain::query()->create([
            'domain' => 'atc.localhost',
            'tenant_id' => $tenantId,
        ]);

        TenantDomainEndpoint::query()->create([
            'tenant_id' => $tenantId,
            'purpose' => 'test',
            'hostname' => 'test.atc.localhost',
            'is_primary' => true,
        ]);

        $resolver = app(SanctumStatefulDomainResolver::class);
        $domains = $resolver->resolve();

        $this->assertContains('atc.localhost', $domains);
        $this->assertContains('atc.localhost:3001', $domains);
        $this->assertContains('test.atc.localhost', $domains);
        $this->assertContains('test.atc.localhost:3001', $domains);
        $this->assertContains('platform.localhost', $domains);
        $this->assertContains('localhost:3001', $domains);
        $this->assertContains(Sanctum::$currentRequestHostPlaceholder, $domains);
    }

    public function test_it_invalidates_cache_when_a_domain_is_created(): void
    {
        Config::set('toweros.sanctum.stateful_domain_cache_ttl', 3600);
        Cache::flush();

        $tenantId = (string) Str::uuid();
        Domain::query()->create([
            'domain' => 'first.localhost',
            'tenant_id' => $tenantId,
        ]);

        $resolver = app(SanctumStatefulDomainResolver::class);
        $this->assertContains('first.localhost', $resolver->resolve());

        Domain::query()->create([
            'domain' => 'second.localhost',
            'tenant_id' => $tenantId,
        ]);

        $this->assertContains('second.localhost', $resolver->resolve());
    }
}
