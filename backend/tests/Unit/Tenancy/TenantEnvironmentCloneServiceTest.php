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
