<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Tenant;
use App\Models\TenantEnvironmentClone;
use App\Modules\Tenancy\Jobs\CloneTenantEnvironmentJob;
use App\Modules\Tenancy\Services\TenantEnvironmentCloneService;
use App\Modules\Tenancy\Services\TenantEnvironmentProvisioningService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

final class TenantEnvironmentCloneServiceTest extends TestCase
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
        Config::set('toweros.tenant_provisioning.auto_seed_holidays', false);
        Config::set('toweros.tenant_environment_clone.skip_preflight', true);
        Config::set('toweros.tenant_files.disk', 'tenant_files');
        Config::set('queue.default', 'sync');
        Config::set('cache.default', 'array');

        DB::purge('central');
        DB::setDefaultConnection('central');

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('slug', 64)->nullable();
            $table->string('brand_domain', 255)->nullable();
            $table->string('environment', 32)->default('production');
            $table->string('tco_sequence_prefix', 8)->nullable();
            $table->string('parent_tenant_id')->nullable();
            $table->string('operator_access_mode', 32)->nullable();
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
        });

        Schema::connection('central')->create('tenant_domain_endpoints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('purpose', 32);
            $table->string('hostname', 255);
            $table->boolean('is_primary')->default(false);
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

        Schema::connection('central')->create('rollout_playbook_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('version', 32);
            $table->string('name');
            $table->string('status', 32)->default('published');
            $table->timestamps();
        });

        Schema::connection('central')->create('tenant_playbook_bindings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('playbook_version_id')->nullable();
            $table->timestamps();
        });
    }

    public function test_rewrites_file_paths_discovered_from_the_schema(): void
    {
        Schema::create('controlled_document_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('stored_path', 512)->nullable();
        });
        Schema::create('notes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('body');
        });

        $source = '756d9d1f-93ce-4444-b959-f33b9e70b354';
        $target = 'd6fa783c-c6f5-4805-a173-da8606a1c20c';
        DB::table('controlled_document_revisions')->insert([
            'id' => (string) Str::uuid(),
            'stored_path' => $source.'/controlled-documents/doc/rev-1/file.pdf',
        ]);
        DB::table('notes')->insert([
            'id' => (string) Str::uuid(),
            'body' => $source,
        ]);

        $updated = app(TenantEnvironmentCloneService::class)->rewriteFilePaths($source, $target);

        $this->assertSame(1, $updated);
        $this->assertSame(
            $target.'/controlled-documents/doc/rev-1/file.pdf',
            DB::table('controlled_document_revisions')->value('stored_path'),
        );
        $this->assertSame($source, DB::table('notes')->value('body'));
    }

    public function test_file_copy_skips_backups_and_keeps_identity_folder(): void
    {
        Storage::fake('tenant_files');
        $source = '756d9d1f-93ce-4444-b959-f33b9e70b354';
        $target = 'd6fa783c-c6f5-4805-a173-da8606a1c20c';
        $disk = Storage::disk('tenant_files');
        $disk->put($source.'/controlled-documents/a.pdf', 'pdf');
        $disk->put($source.'/backups/2026/08/dump.sql.gz', 'sql');
        $disk->put('identity/avatars/user.png', 'png');

        $result = app(TenantEnvironmentCloneService::class)->copyPrefix($source, $target);

        $this->assertSame(1, $result['copied']);
        $this->assertTrue($disk->exists($target.'/controlled-documents/a.pdf'));
        $this->assertFalse($disk->exists($target.'/backups/2026/08/dump.sql.gz'));
        $this->assertTrue($disk->exists('identity/avatars/user.png'));
        $this->assertFalse($disk->exists($target.'/identity/avatars/user.png'));
    }

    public function test_count_mismatch_fails_the_check(): void
    {
        $this->expectException(RuntimeException::class);
        app(TenantEnvironmentCloneService::class)->assertCountsMatch(
            ['users' => 2, 'pending_approvals' => 1, 'controlled_documents' => 1, 'automation_schedules' => 1],
            ['users' => 2, 'pending_approvals' => 0, 'controlled_documents' => 1, 'automation_schedules' => 1],
        );
    }

    public function test_copy_data_queues_a_job_and_skips_admin_bootstrap(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();

        $result = app(TenantEnvironmentProvisioningService::class)->createFromTenant($source, [
            'environment' => 'test',
            'copy_data' => true,
            'migrate' => false,
        ]);

        $this->assertNull($result['initial_admin']);
        $this->assertSame(TenantEnvironmentClone::STATUS_QUEUED, $result['clone']['status']);
        Bus::assertDispatched(CloneTenantEnvironmentJob::class);
        $created = $result['tenant'];
        $this->assertSame('blocked', $created->operator_access_mode);
        $this->assertNull($source->fresh()?->operator_access_mode);
    }

    public function test_production_copy_requires_the_source_domain(): void
    {
        $source = $this->createSourceTenant();

        $this->expectException(ValidationException::class);

        app(TenantEnvironmentProvisioningService::class)->createFromTenant($source, [
            'environment' => 'production',
            'copy_data' => true,
            'confirm_domain' => 'wrong.example',
        ]);
    }

    public function test_second_copy_is_rejected_while_one_is_queued(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();
        app(TenantEnvironmentProvisioningService::class)->createFromTenant($source, [
            'environment' => 'test',
            'copy_data' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(TenantEnvironmentProvisioningService::class)->createFromTenant($source->fresh() ?? $source, [
            'environment' => 'local',
            'copy_data' => true,
        ]);
    }

    public function test_copy_again_queues_a_new_job_and_keeps_the_target_tenant(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $previous = $this->createReadyClone($source, $target);

        $next = app(TenantEnvironmentCloneService::class)->copyAgain(
            $target,
            $previous,
            false,
            'TEST.alliancetowers.com',
            null,
            'ops@toweros.test',
        );

        $this->assertNotSame((string) $previous->id, (string) $next->id);
        $this->assertSame(TenantEnvironmentClone::STATUS_QUEUED, $next->status);
        $this->assertTrue($next->preserve_target);
        $this->assertFalse($next->pause_source);
        $this->assertSame('ops@toweros.test', $next->actor_email);
        $this->assertSame(TenantEnvironmentClone::STATUS_READY, $previous->fresh()?->status);
        $this->assertSame((string) $target->id, (string) $next->target_tenant_id);
        $this->assertSame('blocked', $target->fresh()?->operator_access_mode);
        $this->assertNull($source->fresh()?->operator_access_mode);
        $this->assertNotNull(Tenant::query()->find($target->id));
        Bus::assertDispatched(CloneTenantEnvironmentJob::class);
    }

    public function test_copy_again_can_pause_the_source_until_the_job_finishes(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $previous = $this->createReadyClone($source, $target);

        $next = app(TenantEnvironmentCloneService::class)->copyAgain(
            $target,
            $previous,
            true,
            'test.alliancetowers.com',
            null,
            null,
        );

        $this->assertTrue($next->pause_source);
        $this->assertSame('read_only', $source->fresh()?->operator_access_mode);
        $this->assertSame('blocked', $target->fresh()?->operator_access_mode);
    }

    public function test_copy_again_refuses_to_replace_the_source_workspace(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $previous = $this->createReadyClone($source, $target);

        try {
            app(TenantEnvironmentCloneService::class)->copyAgain(
                $source,
                $previous,
                false,
                'test.alliancetowers.com',
                null,
                null,
            );
            $this->fail('Expected copy again to refuse the source workspace.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('clone', $e->errors());
        }

        $this->assertNull($source->fresh()?->operator_access_mode);
        $this->assertNull($target->fresh()?->operator_access_mode);
        $this->assertSame(1, TenantEnvironmentClone::query()->count());
        Bus::assertNotDispatched(CloneTenantEnvironmentJob::class);
    }

    public function test_copy_again_rejects_the_wrong_target_domain_without_blocking_it(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $previous = $this->createReadyClone($source, $target);

        try {
            app(TenantEnvironmentCloneService::class)->copyAgain(
                $target,
                $previous,
                false,
                'staging.alliancetowers.com',
                null,
                null,
            );
            $this->fail('Expected the target domain confirmation to reject the copy.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('confirm_domain', $e->errors());
        }

        $this->assertNull($target->fresh()?->operator_access_mode);
        $this->assertSame(1, TenantEnvironmentClone::query()->count());
        Bus::assertNotDispatched(CloneTenantEnvironmentJob::class);
    }

    public function test_copy_again_rejects_a_failed_first_copy(): void
    {
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $failed = $this->createReadyClone($source, $target);
        $failed->forceFill([
            'status' => TenantEnvironmentClone::STATUS_FAILED,
            'preserve_target' => false,
        ])->save();

        $this->expectException(ValidationException::class);

        app(TenantEnvironmentCloneService::class)->copyAgain(
            $target,
            $failed->fresh() ?? $failed,
            false,
            'test.alliancetowers.com',
            null,
            null,
        );
    }

    public function test_copy_again_rejects_a_tenant_that_does_not_own_the_copy(): void
    {
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $other = $this->createLinkedTenant('local', 'local.alliancetowers.com');
        $previous = $this->createReadyClone($source, $target);

        $this->expectException(NotFoundHttpException::class);

        app(TenantEnvironmentCloneService::class)->copyAgain(
            $other,
            $previous,
            false,
            'test.alliancetowers.com',
            null,
            null,
        );
    }

    public function test_copy_again_is_rejected_while_another_copy_is_running(): void
    {
        Bus::fake();
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $previous = $this->createReadyClone($source, $target);
        TenantEnvironmentClone::query()->create([
            'id' => (string) Str::uuid(),
            'source_tenant_id' => (string) $source->id,
            'target_tenant_id' => (string) $target->id,
            'status' => TenantEnvironmentClone::STATUS_QUEUED,
            'pause_source' => false,
            'preserve_target' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(TenantEnvironmentCloneService::class)->copyAgain(
            $target,
            $previous,
            false,
            'test.alliancetowers.com',
            null,
            null,
        );
    }

    public function test_cancelling_a_refresh_keeps_the_target_tenant_blocked(): void
    {
        $source = $this->createSourceTenant();
        $target = $this->createLinkedTenant('test', 'test.alliancetowers.com');
        $target->forceFill(['operator_access_mode' => 'blocked'])->save();
        $clone = TenantEnvironmentClone::query()->create([
            'id' => (string) Str::uuid(),
            'source_tenant_id' => (string) $source->id,
            'target_tenant_id' => (string) $target->id,
            'status' => TenantEnvironmentClone::STATUS_QUEUED,
            'pause_source' => false,
            'preserve_target' => true,
            'cancel_requested' => true,
        ]);

        app(TenantEnvironmentCloneService::class)->run((string) $clone->id);

        $this->assertNotNull(Tenant::query()->find($target->id));
        $this->assertSame('test.alliancetowers.com', $target->fresh()?->domains()->first()?->domain);
        $this->assertSame('blocked', $target->fresh()?->operator_access_mode);
        $fresh = $clone->fresh();
        $this->assertSame(TenantEnvironmentClone::STATUS_CANCELLED, $fresh?->status);
        $this->assertNotNull($fresh?->error_message);
    }

    private function createReadyClone(Tenant $source, Tenant $target): TenantEnvironmentClone
    {
        return TenantEnvironmentClone::query()->create([
            'id' => (string) Str::uuid(),
            'source_tenant_id' => (string) $source->id,
            'target_tenant_id' => (string) $target->id,
            'status' => TenantEnvironmentClone::STATUS_READY,
            'pause_source' => false,
            'preserve_target' => false,
            'files_copied' => 3,
            'files_total' => 3,
            'started_at' => now()->subDay(),
            'finished_at' => now()->subDay(),
        ]);
    }

    private function createLinkedTenant(string $environment, string $domain): Tenant
    {
        return Tenant::withoutEvents(function () use ($environment, $domain): Tenant {
            $record = Tenant::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => 'atc',
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

    private function createSourceTenant(): Tenant
    {
        return Tenant::withoutEvents(function (): Tenant {
            $record = Tenant::query()->create([
                'id' => (string) Str::uuid(),
                'slug' => 'atc',
                'brand_domain' => 'alliancetowers.com',
                'environment' => 'staging',
                'plan_tier' => 'starter',
                'subscription_status' => 'active',
                'seat_limit' => 25,
                'mfa_required' => false,
            ]);
            $record->createDomain('staging.alliancetowers.com');

            return $record;
        });
    }
}
