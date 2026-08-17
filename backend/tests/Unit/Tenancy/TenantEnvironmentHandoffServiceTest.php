<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Modules\Identity\Models\TenantUser;
use App\Modules\Tenancy\Services\TenantEnvironmentHandoffService;
use App\Modules\Tenancy\Support\FrontendDevUrl;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class TenantEnvironmentHandoffServiceTest extends TestCase
{
    private string $productionId;

    private string $stagingId;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
        Config::set('app.url', 'http://localhost:8000');
        Config::set('toweros.environment_switch.enabled', true);
        Config::set('toweros.environment_switch.ticket_ttl_seconds', 90);
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

        Schema::connection('central')->create('environment_switch_tickets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('token_hash', 64)->unique();
            $table->string('source_tenant_id');
            $table->string('target_tenant_id');
            $table->uuid('source_user_id');
            $table->string('actor_email', 255);
            $table->string('source_environment', 32)->nullable();
            $table->string('target_environment', 32)->nullable();
            $table->uuid('source_session_id')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->string('consumed_ip', 45)->nullable();
            $table->string('consumed_user_agent', 512)->nullable();
            $table->timestamps();
        });

        // AuthAuditService writes via the default connection in this unit suite.
        Schema::connection('central')->create('auth_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->uuid('session_id')->nullable();
            $table->string('event');
            $table->string('risk_level', 16)->default('low');
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamps();
        });

        $this->productionId = (string) Str::uuid();
        $this->stagingId = (string) Str::uuid();
        $now = now()->toDateTimeString();

        // Staging-only org root (no production-first requirement).
        DB::connection('central')->table('tenants')->insert([
            [
                'id' => $this->stagingId,
                'slug' => 'myapp',
                'brand_domain' => 'localhost',
                'environment' => 'staging',
                'parent_tenant_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => null,
            ],
            [
                'id' => $this->productionId,
                'slug' => 'myapp',
                'brand_domain' => 'localhost',
                'environment' => 'production',
                'parent_tenant_id' => $this->stagingId,
                'created_at' => $now,
                'updated_at' => $now,
                'data' => null,
            ],
        ]);

        DB::connection('central')->table('domains')->insert([
            ['domain' => 'staging.myapp.localhost', 'tenant_id' => $this->stagingId, 'created_at' => $now, 'updated_at' => $now],
            ['domain' => 'app.myapp.localhost', 'tenant_id' => $this->productionId, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function test_mints_ticket_for_sibling_when_org_root_is_staging(): void
    {
        /** @var Tenant $staging */
        $staging = Tenant::query()->with('domains')->findOrFail($this->stagingId);
        $payload = app(TenantEnvironmentHandoffService::class)->mint($staging, $this->switchActor(), 'production');

        $this->assertSame('production', $payload['target_environment']);
        $this->assertSame('app.myapp.localhost', $payload['target_hostname']);
        $this->assertStringContainsString('/auth/environment-handoff?ticket=', $payload['redeem_url']);
        $this->assertStringContainsString('app.myapp.localhost', $payload['redeem_url']);

        $this->assertSame(1, DB::connection('central')->table('environment_switch_tickets')->count());
        $row = DB::connection('central')->table('environment_switch_tickets')->first();
        $this->assertSame($this->stagingId, $row->source_tenant_id);
        $this->assertSame($this->productionId, $row->target_tenant_id);
        $this->assertSame('admin@staging.myapp.localhost', $row->actor_email);
    }

    public function test_rejects_current_environment(): void
    {
        /** @var Tenant $staging */
        $staging = Tenant::query()->with('domains')->findOrFail($this->stagingId);
        $this->expectException(ValidationException::class);
        app(TenantEnvironmentHandoffService::class)->mint($staging, $this->switchActor(), 'staging');
    }

    public function test_rejects_actor_without_switch_permission(): void
    {
        /** @var Tenant $staging */
        $staging = Tenant::query()->with('domains')->findOrFail($this->stagingId);

        $this->expectException(HttpException::class);
        try {
            app(TenantEnvironmentHandoffService::class)->mint(
                $staging,
                $this->switchActor(allowed: false),
                'production',
            );
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_handoff_url_helper(): void
    {
        $url = FrontendDevUrl::tenantEnvironmentHandoffUrl('app.myapp.localhost', 'plain-ticket-secret', 'production');
        $this->assertSame('http://app.myapp.localhost/auth/environment-handoff?ticket=plain-ticket-secret', $url);
    }

    public function test_candidate_emails_remap_bootstrap_admin_domain(): void
    {
        /** @var Tenant $production */
        $production = Tenant::query()->with('domains')->findOrFail($this->productionId);

        $candidates = app(TenantEnvironmentHandoffService::class)->candidateEmailsForTarget(
            'admin@staging.myapp.localhost',
            $production,
        );

        $this->assertSame([
            'admin@staging.myapp.localhost',
            'admin@app.myapp.localhost',
        ], $candidates);
    }

    private function switchActor(bool $allowed = true): TenantUser
    {
        $id = (string) Str::uuid();
        $actor = Mockery::mock(TenantUser::class)->makePartial();
        $actor->forceFill([
            'id' => $id,
            'name' => 'Admin',
            'email' => 'admin@staging.myapp.localhost',
            'is_active' => true,
        ]);
        $actor->shouldReceive('can')->with('workspace:environments:switch')->andReturn($allowed);
        $actor->shouldReceive('isActive')->andReturn(true);
        $actor->shouldReceive('getKey')->andReturn($id);

        return $actor;
    }
}
