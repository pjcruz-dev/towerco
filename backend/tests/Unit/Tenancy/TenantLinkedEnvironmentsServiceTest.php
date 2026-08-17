<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Tenancy\Services\TenantLinkedEnvironmentsService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class TenantLinkedEnvironmentsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('app.url', 'http://localhost:8000');
        Config::set('toweros.environment_switch.enabled', true);
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
            $table->string('slug', 64)->nullable();
            $table->string('brand_domain', 255)->nullable();
            $table->string('environment', 32)->default('production');
            $table->string('parent_tenant_id')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
        });

        Schema::connection('central')->create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
        });

        Schema::connection('central')->create('tenant_sso_configs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('provider', 32)->default('azure');
            $table->boolean('enabled')->default(false);
            $table->timestamps();
        });
    }

    public function test_lists_sibling_environments_with_login_urls(): void
    {
        [, $stagingId] = $this->seedMyappEnvironments();

        /** @var Tenant $staging */
        $staging = Tenant::query()->with('domains')->findOrFail($stagingId);

        $payload = app(TenantLinkedEnvironmentsService::class)->listForTenant($staging);

        $this->assertSame('staging', $payload['current']['environment']);
        $this->assertSame('staging.myapp.localhost', $payload['current']['hostname']);
        $this->assertCount(2, $payload['environments']);
        $this->assertSame(['staging', 'production'], array_column($payload['environments'], 'environment'));
        $this->assertTrue($payload['environments'][0]['is_current']);
        $this->assertFalse($payload['environments'][1]['is_current']);
        $this->assertSame('http://staging.myapp.localhost/login', $payload['environments'][0]['login_url']);
        $this->assertSame('http://app.myapp.localhost/login', $payload['environments'][1]['login_url']);
        $this->assertFalse($payload['environments'][1]['sso_enabled']);
        $this->assertNull($payload['environments'][1]['sso_url']);
        $this->assertSame($payload['environments'][1]['login_url'], $payload['environments'][1]['switch_url']);
        $this->assertSame(['Staging', 'Production'], array_column($payload['environments'], 'label'));
        $this->assertTrue($payload['handoff_supported']);
        $this->assertFalse($payload['environments'][0]['handoff_available']);
        $this->assertTrue($payload['environments'][1]['handoff_available']);
    }

    public function test_handoff_unavailable_when_viewer_cannot_switch(): void
    {
        [, $stagingId] = $this->seedMyappEnvironments();

        /** @var Tenant $staging */
        $staging = Tenant::query()->with('domains')->findOrFail($stagingId);
        $payload = app(TenantLinkedEnvironmentsService::class)->listForTenant($staging, false);

        $this->assertTrue($payload['handoff_supported']);
        $this->assertFalse($payload['environments'][0]['handoff_available']);
        $this->assertFalse($payload['environments'][1]['handoff_available']);
    }

    public function test_prefers_sso_switch_url_when_sibling_sso_enabled(): void
    {
        [$productionId, $stagingId] = $this->seedMyappEnvironments();

        DB::connection('central')->table('tenant_sso_configs')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $productionId,
            'provider' => 'azure',
            'enabled' => true,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        /** @var Tenant $staging */
        $staging = Tenant::query()->with('domains')->findOrFail($stagingId);
        $payload = app(TenantLinkedEnvironmentsService::class)->listForTenant($staging);
        $production = collect($payload['environments'])->firstWhere('environment', 'production');

        $this->assertIsArray($production);
        $this->assertTrue($production['sso_enabled']);
        $this->assertSame(
            'http://localhost:8000/api/v1/auth/sso/azure/redirect?tenant_domain=app.myapp.localhost',
            $production['sso_url'],
        );
        $this->assertSame('http://app.myapp.localhost/login?sso=1', $production['switch_url']);
        $this->assertSame('http://app.myapp.localhost/login', $production['login_url']);
    }

    /**
     * @return array{0: string, 1: string} productionId, stagingId
     */
    private function seedMyappEnvironments(): array
    {
        $productionId = (string) Str::uuid();
        $stagingId = (string) Str::uuid();
        $otherId = (string) Str::uuid();
        $now = now()->toDateTimeString();

        DB::connection('central')->table('tenants')->insert([
            [
                'id' => $productionId,
                'slug' => 'myapp',
                'brand_domain' => 'localhost',
                'environment' => 'production',
                'parent_tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => null,
            ],
            [
                'id' => $stagingId,
                'slug' => 'myapp',
                'brand_domain' => 'localhost',
                'environment' => 'staging',
                'parent_tenant_id' => $productionId,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => null,
            ],
            [
                'id' => $otherId,
                'slug' => 'other',
                'brand_domain' => 'localhost',
                'environment' => 'production',
                'parent_tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => null,
            ],
        ]);

        DB::connection('central')->table('domains')->insert([
            ['domain' => 'app.myapp.localhost', 'tenant_id' => $productionId, 'created_at' => $now, 'updated_at' => $now],
            ['domain' => 'staging.myapp.localhost', 'tenant_id' => $stagingId, 'created_at' => $now, 'updated_at' => $now],
            ['domain' => 'app.other.localhost', 'tenant_id' => $otherId, 'created_at' => $now, 'updated_at' => $now],
        ]);

        return [$productionId, $stagingId];
    }
}
