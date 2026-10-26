<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\TenantEnvironmentClone;
use App\Models\User;
use App\Modules\Tenancy\Jobs\CloneTenantEnvironmentJob;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantEnvironmentCloneCopyAgainTest extends TestCase
{
    private Tenant $source;

    private Tenant $target;

    private TenantEnvironmentClone $clone;

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
        Config::set('toweros.tenant_environment_clone.skip_preflight', true);
        Config::set('toweros.platform_mfa.required', false);
        Config::set('queue.default', 'sync');

        DB::purge('central');
        DB::setDefaultConnection('central');

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('slug', 64)->nullable();
            $table->string('brand_domain', 255)->nullable();
            $table->string('environment', 32)->default('production');
            $table->string('parent_tenant_id')->nullable();
            $table->string('operator_access_mode', 32)->nullable();
            $table->boolean('mfa_required')->default(true);
            $table->string('plan_tier', 32)->default('starter');
            $table->string('subscription_status', 32)->default('active');
            $table->unsignedInteger('seat_limit')->default(25);
            $table->json('enabled_modules')->nullable();
            $table->timestamps();
            $table->json('data')->nullable();
            $table->unique(['slug', 'environment']);
        });

        Schema::connection('central')->create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain')->unique();
            $table->string('tenant_id');
            $table->timestamps();
        });

        Schema::connection('central')->create('tenant_environment_clones', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('source_tenant_id');
            $table->string('target_tenant_id')->nullable();
            $table->string('status', 32);
            $table->boolean('pause_source')->default(false);
            $table->boolean('preserve_target')->default(false);
            $table->boolean('cancel_requested')->default(false);
            $table->string('source_access_mode_before', 32)->nullable();
            $table->json('snapshot_counts')->nullable();
            $table->json('result_counts')->nullable();
            $table->unsignedInteger('files_total')->nullable();
            $table->unsignedInteger('files_copied')->default(0);
            $table->unsignedInteger('paused_schedules')->default(0);
            $table->text('error_message')->nullable();
            $table->string('actor_user_id')->nullable();
            $table->string('actor_email')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        $this->source = $this->makeTenant('staging', 'staging.alliancetowers.com');
        $this->target = $this->makeTenant('test', 'test.alliancetowers.com');
        $this->clone = TenantEnvironmentClone::query()->create([
            'id' => (string) Str::uuid(),
            'source_tenant_id' => (string) $this->source->id,
            'target_tenant_id' => (string) $this->target->id,
            'status' => TenantEnvironmentClone::STATUS_READY,
            'pause_source' => false,
            'preserve_target' => false,
        ]);
    }

    #[Test]
    public function guests_cannot_copy_again(): void
    {
        $this->postJson($this->endpoint((string) $this->target->id), [
            'confirm_domain' => 'test.alliancetowers.com',
        ])->assertUnauthorized();
    }

    #[Test]
    public function viewers_cannot_copy_again(): void
    {
        Passport::actingAs($this->actor('viewer'));

        $this->postJson($this->endpoint((string) $this->target->id), [
            'confirm_domain' => 'test.alliancetowers.com',
        ])->assertForbidden();
    }

    #[Test]
    public function a_wrong_domain_is_rejected(): void
    {
        Passport::actingAs($this->actor('superadmin'));

        $this->postJson($this->endpoint((string) $this->target->id), [
            'confirm_domain' => 'staging.alliancetowers.com',
        ])->assertStatus(422)->assertJsonValidationErrors(['confirm_domain']);

        $this->assertNull($this->target->fresh()?->operator_access_mode);
    }

    #[Test]
    public function another_organizations_tenant_cannot_start_the_copy(): void
    {
        Passport::actingAs($this->actor('superadmin'));
        $other = $this->makeTenant('production', 'other.example', 'other');

        $this->postJson($this->endpoint((string) $other->id), [
            'confirm_domain' => 'test.alliancetowers.com',
        ])->assertNotFound();
    }

    #[Test]
    public function managers_queue_a_refresh_that_keeps_the_target(): void
    {
        Bus::fake();
        $actor = $this->actor('superadmin');
        Passport::actingAs($actor);

        $response = $this->postJson($this->endpoint((string) $this->target->id), [
            'confirm_domain' => 'test.alliancetowers.com',
            'pause_source' => false,
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', TenantEnvironmentClone::STATUS_QUEUED)
            ->assertJsonPath('data.preserve_target', true)
            ->assertJsonPath('data.target_tenant_id', (string) $this->target->id)
            ->assertJsonPath('data.domain', 'test.alliancetowers.com')
            ->assertJsonPath('data.source_environment', 'staging');

        $this->assertSame(TenantEnvironmentClone::STATUS_READY, $this->clone->fresh()?->status);
        $this->assertSame('blocked', $this->target->fresh()?->operator_access_mode);
        $this->assertNotNull(Tenant::query()->find($this->target->id));
        Bus::assertDispatched(CloneTenantEnvironmentJob::class);
    }

    private function endpoint(string $tenantId): string
    {
        return '/api/v1/platform/tenants/'.$tenantId.'/environment-clones/'.$this->clone->id.'/copy-again';
    }

    private function actor(string $role): User
    {
        $user = new User;
        $user->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'Ops',
            'email' => $role.'@toweros.test',
            'password' => 'secret-password',
            'is_platform_admin' => true,
            'platform_role' => $role,
        ]);

        return $user;
    }

    private function makeTenant(string $environment, string $domain, string $slug = 'atc'): Tenant
    {
        return Tenant::withoutEvents(function () use ($environment, $domain, $slug): Tenant {
            $record = Tenant::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => $slug,
                'brand_domain' => 'alliancetowers.com',
                'environment' => $environment,
                'plan_tier' => 'starter',
                'subscription_status' => 'active',
                'seat_limit' => 25,
                'mfa_required' => false,
            ]);
            $record->createDomain($domain);

            return $record;
        });
    }
}
