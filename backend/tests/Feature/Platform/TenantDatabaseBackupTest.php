<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Tenant;
use App\Models\TenantDatabaseBackup;
use App\Modules\Platform\Jobs\CreateTenantDatabaseBackupJob;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TenantDatabaseBackupTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'database.default' => 'central',
            'database.connections.central' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'toweros.tenant_database_backup.enabled' => true,
            'toweros.tenant_database_backup.retention_days' => 15,
            'toweros.tenant_files.disk' => 'tenant_files',
            'filesystems.disks.tenant_files' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/tenant-files-'.Str::random(8)),
            ],
        ]);

        DB::purge('central');
        DB::setDefaultConnection('central');

        Schema::connection('central')->create('tenants', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->timestamps();
            $table->json('data')->nullable();
            $table->string('slug')->nullable();
            $table->string('brand_domain')->nullable();
            $table->string('operator_access_mode')->nullable();
        });

        Schema::connection('central')->create('tenant_database_backups', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id');
            $table->string('status', 32);
            $table->string('storage_path')->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->string('checksum', 64)->nullable();
            $table->string('database_name')->nullable();
            $table->string('triggered_by', 32);
            $table->string('actor_user_id', 36)->nullable();
            $table->string('actor_email')->nullable();
            $table->string('reason', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::connection('central')->create('platform_tenant_audit_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('tenant_id')->nullable();
            $table->string('event_type', 64);
            $table->string('actor_user_id', 36)->nullable();
            $table->string('actor_email')->nullable();
            $table->json('changes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Storage::fake('tenant_files');

        $this->tenant = Tenant::withoutEvents(fn (): Tenant => Tenant::query()->create([
            'id' => (string) Str::uuid(),
            'slug' => 'acme',
        ]));
    }

    #[Test]
    public function queue_create_dispatches_job(): void
    {
        Bus::fake([CreateTenantDatabaseBackupJob::class]);

        $backup = app(TenantDatabaseBackupService::class)->queueCreate($this->tenant, null, 'Test');

        $this->assertSame(TenantDatabaseBackup::STATUS_PENDING, $backup->status);
        Bus::assertDispatched(CreateTenantDatabaseBackupJob::class, function (CreateTenantDatabaseBackupJob $job) use ($backup): bool {
            return $job->backupId === $backup->id;
        });
    }

    #[Test]
    public function list_completed_only_filters_pending_rows(): void
    {
        $completedId = (string) Str::uuid();
        $path = $this->tenant->id.'/backups/2026/08/'.$completedId.'.sql.gz';

        TenantDatabaseBackup::query()->create([
            'id' => $completedId,
            'tenant_id' => (string) $this->tenant->id,
            'status' => TenantDatabaseBackup::STATUS_COMPLETED,
            'storage_path' => $path,
            'byte_size' => 9,
            'triggered_by' => TenantDatabaseBackup::TRIGGER_PLATFORM,
        ]);

        TenantDatabaseBackup::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $this->tenant->id,
            'status' => TenantDatabaseBackup::STATUS_PENDING,
            'triggered_by' => TenantDatabaseBackup::TRIGGER_PLATFORM,
        ]);

        $all = app(TenantDatabaseBackupService::class)->listForTenant($this->tenant, completedOnly: false);
        $completed = app(TenantDatabaseBackupService::class)->listForTenant($this->tenant, completedOnly: true);

        $this->assertCount(2, $all['data']);
        $this->assertCount(1, $completed['data']);
        $this->assertSame($completedId, $completed['data'][0]['id']);
    }

    #[Test]
    public function restore_requires_confirm_slug(): void
    {
        $actor = new \App\Models\User;
        $actor->id = (string) Str::uuid();
        $actor->email = 'ops@toweros.test';

        $backup = TenantDatabaseBackup::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $this->tenant->id,
            'status' => TenantDatabaseBackup::STATUS_COMPLETED,
            'storage_path' => $this->tenant->id.'/backups/2026/08/x.sql.gz',
            'byte_size' => 1,
            'triggered_by' => TenantDatabaseBackup::TRIGGER_PLATFORM,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(TenantDatabaseBackupService::class)->queueRestore(
            $this->tenant,
            $backup,
            $actor,
            'wrong-slug',
            'Need restore',
        );
    }

    #[Test]
    public function prune_expires_old_completed_backups(): void
    {
        $id = (string) Str::uuid();
        $path = $this->tenant->id.'/backups/2026/01/'.$id.'.sql.gz';
        Storage::disk('tenant_files')->put($path, 'old');

        $backup = TenantDatabaseBackup::query()->create([
            'id' => $id,
            'tenant_id' => (string) $this->tenant->id,
            'status' => TenantDatabaseBackup::STATUS_COMPLETED,
            'storage_path' => $path,
            'byte_size' => 3,
            'triggered_by' => TenantDatabaseBackup::TRIGGER_SCHEDULER,
        ]);
        $backup->forceFill([
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ])->save();

        $pruned = app(TenantDatabaseBackupService::class)->pruneExpired();

        $this->assertSame(1, $pruned);
        $this->assertSame(TenantDatabaseBackup::STATUS_EXPIRED, $backup->fresh()?->status);
        Storage::disk('tenant_files')->assertMissing($path);
    }
}
