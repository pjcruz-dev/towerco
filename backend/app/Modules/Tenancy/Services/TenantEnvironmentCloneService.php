<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Services;

use App\Models\Tenant;
use App\Models\TenantEnvironmentClone;
use App\Models\User;
use App\Modules\Platform\Services\PlatformTenantAuditLogger;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseDumpExecutor;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use App\Modules\Tenancy\Jobs\CloneTenantEnvironmentJob;
use App\Modules\Tenancy\Support\TenantOperatorAccessMode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

final class TenantEnvironmentCloneService
{
    /** @var list<string> */
    private const PATH_COLUMNS = ['stored_path', 'file_path'];

    /** @var list<string> */
    private const SESSION_TABLES = [
        'refresh_tokens',
        'refresh_token_families',
        'personal_access_tokens',
        'auth_sessions',
    ];

    public function __construct(
        private readonly TenantDatabaseDumpExecutor $executor,
        private readonly TenantOffboardingService $offboarding,
        private readonly PlatformTenantAuditLogger $audit,
    ) {}

    public function assertCanStart(Tenant $source, string $environment, bool $copyData, ?string $confirmDomain): void
    {
        if (! $copyData) {
            return;
        }

        $this->assertPreflight();

        if ($this->orgHasActiveClone($source)) {
            throw ValidationException::withMessages([
                'copy_data' => [__('An environment copy is already running for this organization.')],
            ]);
        }

        if ($environment === 'production') {
            $expected = strtolower((string) ($source->domains()->first()?->domain ?? ''));
            $given = strtolower(trim((string) $confirmDomain));
            if ($expected === '' || $given !== $expected) {
                throw ValidationException::withMessages([
                    'confirm_domain' => [__('Type the source domain exactly to copy into production.')],
                ]);
            }
        }
    }

    public function queue(
        Tenant $source,
        Tenant $target,
        bool $pauseSource,
        ?string $actorUserId,
        ?string $actorEmail,
    ): TenantEnvironmentClone {
        $previousMode = TenantOperatorAccessMode::normalize($source->operator_access_mode);

        $target->forceFill(['operator_access_mode' => TenantOperatorAccessMode::BLOCKED])->save();

        if ($pauseSource) {
            $source->forceFill(['operator_access_mode' => TenantOperatorAccessMode::READ_ONLY])->save();
        }

        $clone = TenantEnvironmentClone::query()->create([
            'id' => (string) Str::uuid(),
            'source_tenant_id' => (string) $source->id,
            'target_tenant_id' => (string) $target->id,
            'status' => TenantEnvironmentClone::STATUS_QUEUED,
            'pause_source' => $pauseSource,
            'source_access_mode_before' => $previousMode,
            'actor_user_id' => $actorUserId,
            'actor_email' => $actorEmail,
        ]);

        $this->dispatchClone($clone);

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(TenantEnvironmentClone $clone): array
    {
        $clone->loadMissing('targetTenant.domains');
        $target = $clone->targetTenant;

        return [
            'id' => (string) $clone->id,
            'status' => $clone->status,
            'source_tenant_id' => (string) $clone->source_tenant_id,
            'target_tenant_id' => $clone->target_tenant_id,
            'domain' => $target?->domains->first()?->domain,
            'environment' => $target?->environment,
            'pause_source' => (bool) $clone->pause_source,
            'cancel_requested' => (bool) $clone->cancel_requested,
            'snapshot_at' => $clone->started_at?->toIso8601String(),
            'files_copied' => (int) $clone->files_copied,
            'files_total' => $clone->files_total,
            'paused_schedules' => (int) $clone->paused_schedules,
            'counts' => $clone->result_counts ?? $clone->snapshot_counts,
            'error_message' => $clone->error_message,
        ];
    }

    public function run(string $cloneId): void
    {
        $clone = TenantEnvironmentClone::query()->find($cloneId);
        if ($clone === null || ! $clone->isActive()) {
            return;
        }

        try {
            $this->throwIfCancelled($clone);
            $clone->forceFill([
                'status' => TenantEnvironmentClone::STATUS_COPYING_DATABASE,
                'started_at' => $clone->started_at ?? now(),
                'error_message' => null,
            ])->save();

            $source = Tenant::query()->findOrFail($clone->source_tenant_id);
            $target = Tenant::query()->findOrFail($clone->target_tenant_id);

            $counts = $this->snapshotCounts($source);
            $clone->forceFill(['snapshot_counts' => $counts])->save();

            $gzipPath = tempnam(sys_get_temp_dir(), 'toweros-clone-');
            if ($gzipPath === false) {
                throw new RuntimeException('Could not allocate a temporary dump file.');
            }
            $gzipPath .= '.sql.gz';

            try {
                $this->executor->dumpToGzipFile($this->mysqlConnectionForTenant($source), $gzipPath);
                $this->throwIfCancelled($clone);
                $this->executor->restoreFromGzipFile($this->mysqlConnectionForTenant($target), $gzipPath);
            } finally {
                @unlink($gzipPath);
            }

            $paused = $this->prepareCopiedDatabase($target, (string) $source->id, (string) $target->id);
            $this->copySsoConfig($source, $target);

            $this->throwIfCancelled($clone);
            $clone->forceFill([
                'status' => TenantEnvironmentClone::STATUS_COPYING_FILES,
                'paused_schedules' => $paused,
            ])->save();

            $this->deletePrefix((string) $target->id);
            $copied = $this->copyFiles($clone, (string) $source->id, (string) $target->id);

            $this->throwIfCancelled($clone);
            $clone->forceFill(['status' => TenantEnvironmentClone::STATUS_VERIFYING])->save();

            $missing = $this->missingFilePaths($target);
            if ($missing !== []) {
                throw new RuntimeException(
                    'Copied workspace is missing '.count($missing).' file(s). First: '.$missing[0],
                );
            }

            $actual = $this->snapshotCounts($target);
            $this->assertCountsMatch($counts, $actual);

            $target->forceFill(['operator_access_mode' => null])->save();
            $clone->forceFill([
                'status' => TenantEnvironmentClone::STATUS_READY,
                'result_counts' => $actual,
                'files_copied' => $copied['copied'],
                'files_total' => $copied['total'],
                'finished_at' => now(),
                'error_message' => null,
            ])->save();

            $this->writeAudit($clone->fresh() ?? $clone, $target, false);
        } catch (TenantEnvironmentCloneCancelled $e) {
            $this->finishCancelled($clone->fresh() ?? $clone);
        } catch (Throwable $e) {
            $fresh = $clone->fresh() ?? $clone;
            Log::error('tenant.environment.clone_failed', [
                'clone_id' => (string) $fresh->id,
                'source_tenant_id' => (string) $fresh->source_tenant_id,
                'target_tenant_id' => $fresh->target_tenant_id,
                'message' => $e->getMessage(),
            ]);
            if ($fresh->status !== TenantEnvironmentClone::STATUS_CANCELLED) {
                $fresh->forceFill([
                    'status' => TenantEnvironmentClone::STATUS_FAILED,
                    'error_message' => Str::limit($e->getMessage(), 500),
                    'finished_at' => now(),
                ])->save();
                $target = $fresh->target_tenant_id !== null
                    ? Tenant::query()->find($fresh->target_tenant_id)
                    : null;
                if ($target !== null) {
                    $target->forceFill(['operator_access_mode' => TenantOperatorAccessMode::BLOCKED])->save();
                    $this->writeAudit($fresh, $target, true);
                }
            }
        } finally {
            $this->restoreSourceAccess($clone->fresh() ?? $clone);
        }
    }

    public function latestForTenant(Tenant $tenant): ?TenantEnvironmentClone
    {
        return TenantEnvironmentClone::query()
            ->where(function ($query) use ($tenant): void {
                $query->where('source_tenant_id', (string) $tenant->id)
                    ->orWhere('target_tenant_id', (string) $tenant->id);
            })
            ->latest()
            ->first();
    }

    public function findForTenant(Tenant $tenant, string $cloneId): TenantEnvironmentClone
    {
        $clone = TenantEnvironmentClone::query()->find($cloneId);
        if ($clone === null) {
            abort(404);
        }

        $this->assertOwned($tenant, $clone);

        return $clone;
    }

    public function retry(Tenant $tenant, TenantEnvironmentClone $clone): TenantEnvironmentClone
    {
        $this->assertOwned($tenant, $clone);
        if ($clone->status !== TenantEnvironmentClone::STATUS_FAILED || $clone->target_tenant_id === null) {
            throw ValidationException::withMessages([
                'clone' => [__('Only a failed copy can be retried.')],
            ]);
        }

        if ($this->orgHasActiveClone(Tenant::query()->findOrFail($clone->source_tenant_id), (string) $clone->id)) {
            throw ValidationException::withMessages([
                'clone' => [__('An environment copy is already running for this organization.')],
            ]);
        }

        $target = Tenant::query()->findOrFail($clone->target_tenant_id);
        $target->forceFill(['operator_access_mode' => TenantOperatorAccessMode::BLOCKED])->save();

        $source = Tenant::query()->findOrFail($clone->source_tenant_id);
        if ($clone->pause_source) {
            $clone->source_access_mode_before = TenantOperatorAccessMode::normalize($source->operator_access_mode);
            $source->forceFill(['operator_access_mode' => TenantOperatorAccessMode::READ_ONLY])->save();
        }

        $clone->forceFill([
            'status' => TenantEnvironmentClone::STATUS_QUEUED,
            'cancel_requested' => false,
            'error_message' => null,
            'files_copied' => 0,
            'files_total' => null,
            'result_counts' => null,
            'finished_at' => null,
            'started_at' => null,
        ])->save();

        $this->dispatchClone($clone);

        return $clone->fresh() ?? $clone;
    }

    public function requestCancel(Tenant $tenant, TenantEnvironmentClone $clone): TenantEnvironmentClone
    {
        $this->assertOwned($tenant, $clone);
        if (! $clone->isActive()) {
            throw ValidationException::withMessages([
                'clone' => [__('This copy is not running.')],
            ]);
        }

        $clone->forceFill(['cancel_requested' => true])->save();

        return $clone;
    }

    public function discard(Tenant $tenant, TenantEnvironmentClone $clone): TenantEnvironmentClone
    {
        $this->assertOwned($tenant, $clone);

        if ($clone->isActive()) {
            $clone->forceFill(['cancel_requested' => true])->save();

            return $clone;
        }

        if (! in_array($clone->status, [
            TenantEnvironmentClone::STATUS_FAILED,
            TenantEnvironmentClone::STATUS_READY,
            TenantEnvironmentClone::STATUS_CANCELLED,
        ], true)) {
            throw ValidationException::withMessages([
                'clone' => [__('This copy cannot be discarded.')],
            ]);
        }

        $this->deleteTargetTenant($clone);
        $clone->forceFill([
            'status' => TenantEnvironmentClone::STATUS_DISCARDED,
            'finished_at' => now(),
        ])->save();

        return $clone->fresh() ?? $clone;
    }

    /**
     * @param  array<string, int>  $expected
     * @param  array<string, int>  $actual
     */
    public function assertCountsMatch(array $expected, array $actual): void
    {
        foreach (['users', 'pending_approvals', 'controlled_documents', 'automation_schedules'] as $key) {
            $left = (int) ($expected[$key] ?? 0);
            $right = (int) ($actual[$key] ?? 0);
            if ($left !== $right) {
                throw new RuntimeException("Count mismatch for {$key}: snapshot {$left}, copy {$right}.");
            }
        }
    }

    /**
     * @return array<string, int>
     */
    public function snapshotCounts(Tenant $tenant): array
    {
        return $this->withTenant($tenant, function (): array {
            return [
                'users' => $this->countTable('users'),
                'pending_approvals' => Schema::hasTable('e_approval_request_approvals')
                    ? (int) DB::table('e_approval_request_approvals')->where('status', 'pending')->count()
                    : 0,
                'controlled_documents' => $this->countTable('controlled_documents'),
                'automation_schedules' => $this->countTable('dyn_scheduled_tasks'),
            ];
        });
    }

    public function rewriteFilePaths(string $sourceId, string $targetId): int
    {
        $updated = 0;
        foreach ($this->pathColumns() as [$table, $column]) {
            $updated += DB::update(
                sprintf(
                    'UPDATE %s SET %s = REPLACE(%s, ?, ?) WHERE %s LIKE ?',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($column),
                    $this->quoteIdentifier($column),
                    $this->quoteIdentifier($column),
                ),
                [$sourceId, $targetId, $sourceId.'%'],
            );
        }

        return $updated;
    }

    /**
     * @return array{total: int, copied: int}
     */
    public function copyPrefix(string $sourceId, string $targetId, ?callable $onProgress = null): array
    {
        $disk = Storage::disk($this->filesDisk());
        $skip = $sourceId.'/backups/';
        $files = array_values(array_filter(
            $disk->allFiles($sourceId),
            static fn (string $path): bool => ! str_starts_with($path, $skip),
        ));
        $total = count($files);
        $copied = 0;

        foreach ($files as $path) {
            if (! str_starts_with($path, $sourceId.'/') && $path !== $sourceId) {
                continue;
            }
            $suffix = substr($path, strlen($sourceId));
            $disk->copy($path, $targetId.$suffix);
            $copied++;
            if ($onProgress !== null && ($copied === $total || $copied % 10 === 0)) {
                $onProgress($copied, $total);
            }
        }

        return ['total' => $total, 'copied' => $copied];
    }

    private function assertPreflight(): void
    {
        if ((bool) config('toweros.tenant_environment_clone.skip_preflight', false)) {
            return;
        }

        $finder = new ExecutableFinder;
        foreach ([
            (string) config('toweros.tenant_database_backup.mysqldump_path', 'mysqldump'),
            (string) config('toweros.tenant_database_backup.mysql_path', 'mysql'),
        ] as $binary) {
            $found = str_contains($binary, DIRECTORY_SEPARATOR) || str_contains($binary, '/')
                ? (is_file($binary) ? $binary : null)
                : $finder->find($binary);
            if ($found === null) {
                throw ValidationException::withMessages([
                    'copy_data' => [__('The database copy tools are not available on this server (:binary).', ['binary' => $binary])],
                ]);
            }
        }

        $disk = $this->filesDisk();
        if (! is_array(config('filesystems.disks.'.$disk))) {
            throw ValidationException::withMessages([
                'copy_data' => [__('The tenant files disk is not configured.')],
            ]);
        }
    }

    private function orgHasActiveClone(Tenant $source, ?string $exceptId = null): bool
    {
        $ids = Tenant::query()
            ->where('slug', $source->slug)
            ->pluck('id')
            ->push($source->id)
            ->unique()
            ->all();

        $query = TenantEnvironmentClone::query()
            ->whereIn('status', TenantEnvironmentClone::ACTIVE_STATUSES)
            ->whereNotNull('target_tenant_id')
            ->where(function ($inner) use ($ids): void {
                $inner->whereIn('source_tenant_id', $ids)
                    ->orWhereIn('target_tenant_id', $ids);
            });

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function prepareCopiedDatabase(Tenant $target, string $sourceId, string $targetId): int
    {
        return $this->withTenant($target, function () use ($sourceId, $targetId): int {
            $this->rewriteFilePaths($sourceId, $targetId);
            $paused = 0;
            if (Schema::hasTable('dyn_scheduled_tasks')) {
                $paused = DB::table('dyn_scheduled_tasks')->where('is_active', true)->update(['is_active' => false]);
            }
            $this->clearSessions();

            return $paused;
        });
    }

    private function clearSessions(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach (self::SESSION_TABLES as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->delete();
                }
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }
    }

    private function copySsoConfig(Tenant $source, Tenant $target): void
    {
        if (! Schema::connection('central')->hasTable('tenant_sso_configs')) {
            return;
        }

        $central = DB::connection('central');
        $existing = $central->table('tenant_sso_configs')->where('tenant_id', $target->id)->exists();
        if ($existing) {
            return;
        }

        $rows = $central->table('tenant_sso_configs')->where('tenant_id', $source->id)->get();
        foreach ($rows as $row) {
            $record = (array) $row;
            $record['id'] = (string) Str::uuid();
            $record['tenant_id'] = (string) $target->id;
            $record['created_at'] = now();
            $record['updated_at'] = now();
            $central->table('tenant_sso_configs')->insert($record);
        }
    }

    /**
     * @return array{total: int, copied: int}
     */
    private function copyFiles(TenantEnvironmentClone $clone, string $sourceId, string $targetId): array
    {
        return $this->copyPrefix($sourceId, $targetId, function (int $copied, int $total) use ($clone): void {
            $this->throwIfCancelled($clone);
            $clone->forceFill([
                'files_copied' => $copied,
                'files_total' => $total,
            ])->save();
        });
    }

    /**
     * @return list<string>
     */
    private function missingFilePaths(Tenant $target): array
    {
        $prefix = ((string) $target->id).'/';

        return $this->withTenant($target, function () use ($prefix): array {
            $disk = Storage::disk($this->filesDisk());
            $missing = [];
            foreach ($this->pathColumns() as [$table, $column]) {
                DB::table($table)
                    ->whereNotNull($column)
                    ->where($column, '!=', '')
                    ->orderBy($column)
                    ->select([$column])
                    ->chunk(200, function ($rows) use ($disk, $column, $prefix, &$missing): bool {
                        foreach ($rows as $row) {
                            $path = (string) $row->{$column};
                            // Generated exports live on the local app disk, not the tenant file prefix.
                            if ($path === '' || ! str_starts_with($path, $prefix)) {
                                continue;
                            }
                            if (! $disk->exists($path)) {
                                $missing[] = $path;
                            }
                            if (count($missing) >= 20) {
                                return false;
                            }
                        }

                        return true;
                    });
                if (count($missing) >= 20) {
                    break;
                }
            }

            return $missing;
        });
    }

    private function finishCancelled(TenantEnvironmentClone $clone): void
    {
        $this->deleteTargetTenant($clone);
        $clone->forceFill([
            'status' => TenantEnvironmentClone::STATUS_CANCELLED,
            'finished_at' => now(),
            'error_message' => null,
        ])->save();
    }

    private function deleteTargetTenant(TenantEnvironmentClone $clone): void
    {
        if ($clone->target_tenant_id === null) {
            return;
        }

        $target = Tenant::query()->find($clone->target_tenant_id);
        if ($target === null) {
            return;
        }

        $this->offboarding->deleteTenant($target, [
            'confirmation' => (string) $target->id,
            'cascade' => false,
        ]);
    }

    private function dispatchClone(TenantEnvironmentClone $clone): void
    {
        $connection = (string) config('queue.default');
        if ($connection === 'sync') {
            $connection = 'redis';
        }

        CloneTenantEnvironmentJob::dispatch((string) $clone->id)->onConnection($connection);
    }

    private function deletePrefix(string $tenantId): void
    {
        $disk = Storage::disk($this->filesDisk());
        $files = $disk->allFiles($tenantId);
        if ($files !== []) {
            $disk->delete($files);
        }
    }

    private function restoreSourceAccess(TenantEnvironmentClone $clone): void
    {
        if (! $clone->pause_source) {
            return;
        }

        $source = Tenant::query()->find($clone->source_tenant_id);
        if ($source === null) {
            return;
        }

        $source->forceFill([
            'operator_access_mode' => TenantOperatorAccessMode::normalize($clone->source_access_mode_before),
        ])->save();
    }

    private function throwIfCancelled(TenantEnvironmentClone $clone): void
    {
        $fresh = $clone->fresh();
        if ($fresh !== null && $fresh->cancel_requested) {
            throw new TenantEnvironmentCloneCancelled;
        }
    }

    private function writeAudit(TenantEnvironmentClone $clone, Tenant $target, bool $failed): void
    {
        if (! Schema::connection('central')->hasTable('platform_tenant_audit_logs')) {
            return;
        }

        $actor = $clone->actor_user_id !== null
            ? User::query()->find($clone->actor_user_id)
            : null;

        $this->audit->log(
            PlatformTenantAuditEventType::TENANT_ENVIRONMENT_DATA_CLONED,
            $target,
            $actor instanceof User ? $actor : null,
            null,
            [
                'clone_id' => (string) $clone->id,
                'source_tenant_id' => (string) $clone->source_tenant_id,
                'status' => $failed ? TenantEnvironmentClone::STATUS_FAILED : $clone->status,
                'counts' => $clone->result_counts ?? $clone->snapshot_counts,
                'files_copied' => $clone->files_copied,
                'files_total' => $clone->files_total,
                'paused_schedules' => $clone->paused_schedules,
                'actor_email' => $clone->actor_email,
            ],
        );
    }

    private function assertOwned(Tenant $tenant, TenantEnvironmentClone $clone): void
    {
        $tenantId = (string) $tenant->id;
        if ($clone->source_tenant_id !== $tenantId && $clone->target_tenant_id !== $tenantId) {
            abort(404);
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function withTenant(Tenant $tenant, callable $callback): mixed
    {
        tenancy()->initialize($tenant);

        try {
            return $callback();
        } finally {
            tenancy()->end();
        }
    }

    private function countTable(string $table): int
    {
        return Schema::hasTable($table) ? (int) DB::table($table)->count() : 0;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function pathColumns(): array
    {
        $found = [];
        $listing = Schema::getTableListing(null, false);
        foreach ($listing as $table) {
            if (! preg_match('/^[A-Za-z0-9_]+$/', $table) || str_starts_with($table, 'sqlite_')) {
                continue;
            }
            foreach (self::PATH_COLUMNS as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $found[] = [$table, $column];
                }
            }
        }

        return $found;
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
            throw new RuntimeException('Refusing unsafe SQL identifier.');
        }

        return '`'.$identifier.'`';
    }

    /**
     * @return array{host: string, port: int|string, username: string, password: string, database: string}
     */
    private function mysqlConnectionForTenant(Tenant $tenant): array
    {
        $name = $tenant->database()->getName();
        if (! is_string($name) || $name === '') {
            throw new RuntimeException('Tenant database name is not configured.');
        }

        $central = config('database.connections.central');
        if (! is_array($central)) {
            throw new RuntimeException('Central database connection is not configured.');
        }

        return [
            'host' => (string) ($central['host'] ?? '127.0.0.1'),
            'port' => $central['port'] ?? 3306,
            'username' => (string) ($central['username'] ?? ''),
            'password' => (string) ($central['password'] ?? ''),
            'database' => $name,
        ];
    }

    private function filesDisk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }
}

final class TenantEnvironmentCloneCancelled extends RuntimeException {}
