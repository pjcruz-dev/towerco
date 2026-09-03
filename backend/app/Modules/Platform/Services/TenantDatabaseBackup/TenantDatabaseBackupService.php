<?php

declare(strict_types=1);

namespace App\Modules\Platform\Services\TenantDatabaseBackup;

use App\Models\Tenant;
use App\Models\TenantDatabaseBackup;
use App\Models\User;
use App\Modules\Platform\Jobs\CreateTenantDatabaseBackupJob;
use App\Modules\Platform\Jobs\RestoreTenantDatabaseBackupJob;
use App\Modules\Platform\Services\PlatformTenantAuditLogger;
use App\Modules\Platform\Support\PlatformTenantAuditEventType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class TenantDatabaseBackupService
{
    public function __construct(
        private readonly TenantDatabaseDumpExecutor $executor,
        private readonly PlatformTenantAuditLogger $audit,
    ) {}

    public function assertFeatureEnabled(): void
    {
        if (! (bool) config('toweros.tenant_database_backup.enabled', true)) {
            throw ValidationException::withMessages([
                'backup' => [__('Tenant database backups are disabled.')],
            ]);
        }
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{total: int, completed: int, storage_bytes: int, latest_at: string|null, retention_days: int}}
     */
    public function listForTenant(Tenant $tenant, bool $completedOnly = false): array
    {
        $query = TenantDatabaseBackup::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('status', '!=', TenantDatabaseBackup::STATUS_EXPIRED)
            ->orderByDesc('created_at');

        if ($completedOnly) {
            $query->where('status', TenantDatabaseBackup::STATUS_COMPLETED);
        }

        $rows = $query->get();
        $completed = $rows->where('status', TenantDatabaseBackup::STATUS_COMPLETED);
        $latest = $completed->first();

        return [
            'data' => $rows->map(fn (TenantDatabaseBackup $backup): array => $this->toPayload($backup))->values()->all(),
            'meta' => [
                'total' => $rows->count(),
                'completed' => $completed->count(),
                'storage_bytes' => (int) $completed->sum('byte_size'),
                'latest_at' => $latest?->finished_at?->toIso8601String() ?? $latest?->created_at?->toIso8601String(),
                'retention_days' => (int) config('toweros.tenant_database_backup.retention_days', 15),
            ],
        ];
    }

    public function findForTenant(Tenant $tenant, string $backupId): TenantDatabaseBackup
    {
        $backup = TenantDatabaseBackup::query()
            ->where('tenant_id', (string) $tenant->id)
            ->where('id', $backupId)
            ->first();

        if ($backup === null) {
            abort(404, __('Backup not found.'));
        }

        return $backup;
    }

    public function queueCreate(Tenant $tenant, ?User $actor = null, ?string $reason = null, string $triggeredBy = TenantDatabaseBackup::TRIGGER_PLATFORM): TenantDatabaseBackup
    {
        $this->assertFeatureEnabled();
        $this->assertNoConcurrentWork($tenant);

        $connection = $this->mysqlConnectionForTenant($tenant);
        try {
            $this->executor->assertReady('create', $connection);
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'backup' => [__('Backup preflight failed: :message', ['message' => Str::limit($e->getMessage(), 400)])],
            ]);
        }

        $backup = TenantDatabaseBackup::query()->create([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->id,
            'status' => TenantDatabaseBackup::STATUS_PENDING,
            'progress_percent' => 5,
            'progress_message' => 'Queued — waiting to start dump',
            'database_name' => $connection['database'],
            'triggered_by' => $triggeredBy,
            'actor_user_id' => $actor?->id,
            'actor_email' => $actor?->email,
            'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
        ]);

        CreateTenantDatabaseBackupJob::dispatch($backup->id)->afterResponse();

        $this->audit->log(
            PlatformTenantAuditEventType::TENANT_BACKUP_CREATED,
            $tenant,
            $actor,
            null,
            [
                'backup_id' => $backup->id,
                'triggered_by' => $triggeredBy,
                'reason' => $backup->reason,
            ],
        );

        return $backup->fresh() ?? $backup;
    }

    /**
     * Import a previously downloaded .sql / .sql.gz into the backup catalog (does not restore yet).
     * Operator can then use Restore on the new row.
     */
    public function importUploadedArchive(
        Tenant $tenant,
        UploadedFile $file,
        User $actor,
        ?string $reason = null,
    ): TenantDatabaseBackup {
        $this->assertFeatureEnabled();

        $original = strtolower((string) $file->getClientOriginalName());
        $isGzip = str_ends_with($original, '.sql.gz') || str_ends_with($original, '.gz');
        $isSql = str_ends_with($original, '.sql');
        if (! $isGzip && ! $isSql) {
            throw ValidationException::withMessages([
                'file' => [__('Upload a .sql or .sql.gz dump exported from TowerOS backups.')],
            ]);
        }

        $maxKb = max(1024, (int) config('toweros.tenant_database_backup.upload_max_kb', 32768));
        $size = (int) $file->getSize();
        if ($size <= 0 || $size > ($maxKb * 1024)) {
            throw ValidationException::withMessages([
                'file' => [__('Backup file exceeds the upload limit (:max MB).', ['max' => (int) ceil($maxKb / 1024)])],
            ]);
        }

        $backupId = (string) Str::uuid();
        $storagePath = $this->storagePathFor($tenant, $backupId);
        $tmpGz = tempnam(sys_get_temp_dir(), 'toweros-tdb-upload-');
        if ($tmpGz === false) {
            throw ValidationException::withMessages([
                'file' => [__('Could not allocate temporary upload storage.')],
            ]);
        }
        $tmpGzPath = $tmpGz.'.sql.gz';
        @unlink($tmpGz);

        try {
            if ($isGzip) {
                $this->normalizeUploadedGzipToFile($file->getRealPath() ?: '', $tmpGzPath);
            } else {
                $this->gzipPlainSqlFile($file->getRealPath() ?: '', $tmpGzPath);
            }

            $this->assertLooksLikeMysqlDump($tmpGzPath);

            $bytes = file_get_contents($tmpGzPath);
            if ($bytes === false || $bytes === '') {
                throw ValidationException::withMessages([
                    'file' => [__('Uploaded backup archive is empty.')],
                ]);
            }

            Storage::disk($this->disk())->put($storagePath, $bytes);

            $backup = TenantDatabaseBackup::query()->create([
                'id' => $backupId,
                'tenant_id' => (string) $tenant->id,
                'status' => TenantDatabaseBackup::STATUS_COMPLETED,
                'progress_percent' => 100,
                'progress_message' => 'Uploaded — ready to restore',
                'storage_path' => $storagePath,
                'byte_size' => strlen($bytes),
                'checksum' => hash('sha256', $bytes),
                'database_name' => $tenant->database()->getName(),
                'triggered_by' => TenantDatabaseBackup::TRIGGER_UPLOAD,
                'actor_user_id' => $actor->id,
                'actor_email' => $actor->email,
                'reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : 'Uploaded backup archive',
                'started_at' => now(),
                'finished_at' => now(),
            ]);

            $this->audit->log(
                PlatformTenantAuditEventType::TENANT_BACKUP_UPLOADED,
                $tenant,
                $actor,
                null,
                [
                    'backup_id' => $backup->id,
                    'original_name' => $file->getClientOriginalName(),
                    'byte_size' => $backup->byte_size,
                    'reason' => $backup->reason,
                ],
            );

            return $backup;
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            @unlink($tmpGzPath);
            try {
                Storage::disk($this->disk())->delete($storagePath);
            } catch (Throwable) {
            }

            throw ValidationException::withMessages([
                'file' => [Str::limit($e->getMessage(), 400)],
            ]);
        } finally {
            @unlink($tmpGzPath);
        }
    }

    public function queueRestore(
        Tenant $tenant,
        TenantDatabaseBackup $backup,
        User $actor,
        string $confirmSlug,
        string $reason,
    ): TenantDatabaseBackup {
        $this->assertFeatureEnabled();

        if ((string) $backup->tenant_id !== (string) $tenant->id) {
            abort(404, __('Backup not found.'));
        }

        if (! $backup->isCompleted()) {
            throw ValidationException::withMessages([
                'backup' => [__('Only completed backups can be restored.')],
            ]);
        }

        $expected = strtolower(trim((string) ($tenant->slug ?: $tenant->brand_domain ?: '')));
        $provided = strtolower(trim($confirmSlug));
        if ($expected === '' || $provided === '' || $provided !== $expected) {
            throw ValidationException::withMessages([
                'confirm' => [__('Type the tenant slug (or brand domain) exactly to confirm restore.')],
            ]);
        }

        $this->assertNoConcurrentWork($tenant, $backup->id);
        $this->assertStoragePathOwnedByTenant($tenant, (string) $backup->storage_path);

        $connection = $this->mysqlConnectionForTenant($tenant);
        try {
            $this->assertRestoreArchiveValid($tenant, $backup);
            $this->executor->assertReady('restore', $connection);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw ValidationException::withMessages([
                'restore' => [__('Restore preflight failed: :message', ['message' => Str::limit($e->getMessage(), 400)])],
            ]);
        }

        $backup->status = TenantDatabaseBackup::STATUS_RESTORING;
        $backup->progress_percent = 5;
        $backup->progress_message = 'Queued — restore will start shortly';
        $backup->error_message = null;
        $backup->started_at = now();
        $backup->finished_at = null;
        $backup->save();

        try {
            // afterResponse: with QUEUE_CONNECTION=sync, restore still runs in-process but
            // AFTER the 202 is sent — avoids axios 20s timeouts aborting a live mysql import.
            RestoreTenantDatabaseBackupJob::dispatch(
                $backup->id,
                (string) $tenant->id,
                (string) $actor->id,
                (string) $actor->email,
                trim($reason),
            )->afterResponse();
        } catch (Throwable $e) {
            $backup->status = TenantDatabaseBackup::STATUS_COMPLETED;
            $backup->error_message = 'Restore failed to queue: '.Str::limit($e->getMessage(), 1800);
            $backup->finished_at = now();
            $backup->save();

            throw ValidationException::withMessages([
                'restore' => [Str::limit($e->getMessage(), 500)],
            ]);
        }

        $this->audit->log(
            PlatformTenantAuditEventType::TENANT_BACKUP_RESTORE_QUEUED,
            $tenant,
            $actor,
            null,
            [
                'backup_id' => $backup->id,
                'reason' => trim($reason),
            ],
        );

        return $backup->fresh() ?? $backup;
    }

    public function deleteBackup(Tenant $tenant, TenantDatabaseBackup $backup, ?User $actor = null): void
    {
        if ((string) $backup->tenant_id !== (string) $tenant->id) {
            abort(404, __('Backup not found.'));
        }

        if ($backup->isInFlight()) {
            throw ValidationException::withMessages([
                'backup' => [__('Cannot delete a backup that is still running.')],
            ]);
        }

        $this->deleteStorageObject($backup);

        $backupId = $backup->id;
        $backup->delete();

        $this->audit->log(
            PlatformTenantAuditEventType::TENANT_BACKUP_DELETED,
            $tenant,
            $actor,
            null,
            ['backup_id' => $backupId],
        );
    }

    /**
     * Authenticated stream download.
     * Serves ungzipped .sql so Windows users can open the file directly
     * (Explorer "Extract all" does not support gzip and yields empty files).
     * Objects remain stored as .sql.gz on the tenant_files disk.
     */
    public function streamDownload(Tenant $tenant, TenantDatabaseBackup $backup): StreamedResponse
    {
        if ((string) $backup->tenant_id !== (string) $tenant->id) {
            abort(404, __('Backup not found.'));
        }

        if (! $backup->isCompleted() || $backup->storage_path === null || $backup->storage_path === '') {
            throw ValidationException::withMessages([
                'backup' => [__('Backup file is not available for download.')],
            ]);
        }

        $this->assertStoragePathOwnedByTenant($tenant, $backup->storage_path);

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($backup->storage_path)) {
            throw ValidationException::withMessages([
                'backup' => [__('Backup file is missing from storage.')],
            ]);
        }

        $gzip = $disk->get($backup->storage_path);
        if (! is_string($gzip) || $gzip === '') {
            throw ValidationException::withMessages([
                'backup' => [__('Backup file is empty.')],
            ]);
        }

        $sql = @gzdecode($gzip);
        if ($sql === false || $sql === '') {
            throw ValidationException::withMessages([
                'backup' => [__('Backup archive could not be decompressed.')],
            ]);
        }

        $base = basename($backup->storage_path);
        $filename = str_ends_with($base, '.sql.gz')
            ? substr($base, 0, -3) // drop .gz → *.sql
            : (str_ends_with($base, '.gz') ? substr($base, 0, -3).'.sql' : $base.'.sql');

        return response()->streamDownload(static function () use ($sql): void {
            echo $sql;
        }, $filename, [
            'Content-Type' => 'application/sql; charset=UTF-8',
            'Content-Length' => (string) strlen($sql),
            'X-Content-Type-Options' => 'nosniff',
            // Prevent proxies/browsers from treating this as transport-level gzip.
            'Content-Encoding' => 'identity',
        ]);
    }

    /**
     * @return array{url: string, expires_at: string, filename: string}
     *
     * @deprecated Prefer streamDownload for authenticated clients; kept for S3 signed-url callers.
     */
    public function downloadUrl(Tenant $tenant, TenantDatabaseBackup $backup): array
    {
        if ((string) $backup->tenant_id !== (string) $tenant->id) {
            abort(404, __('Backup not found.'));
        }

        if (! $backup->isCompleted() || $backup->storage_path === null || $backup->storage_path === '') {
            throw ValidationException::withMessages([
                'backup' => [__('Backup file is not available for download.')],
            ]);
        }

        $this->assertStoragePathOwnedByTenant($tenant, $backup->storage_path);

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($backup->storage_path)) {
            throw ValidationException::withMessages([
                'backup' => [__('Backup file is missing from storage.')],
            ]);
        }

        $minutes = max(1, (int) config('toweros.tenant_database_backup.signed_url_minutes', 30));
        $expires = now()->addMinutes($minutes);
        $filename = basename($backup->storage_path);

        try {
            $url = $disk->temporaryUrl($backup->storage_path, $expires);
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'backup' => [__('Signed download URLs are unavailable for this storage disk. Use the authenticated download endpoint.')],
            ]);
        }

        return [
            'url' => $url,
            'expires_at' => $expires->toIso8601String(),
            'filename' => $filename,
        ];
    }

    public function runCreate(Tenant $tenant, TenantDatabaseBackup $backup): void
    {
        $backup->status = TenantDatabaseBackup::STATUS_RUNNING;
        $backup->started_at = now();
        $backup->error_message = null;
        $this->setProgress($backup, 15, 'Validating tools and database…');

        $tmp = tempnam(sys_get_temp_dir(), 'toweros-tdb-');
        if ($tmp === false) {
            $this->markFailed($backup, 'Could not allocate temporary file.');

            return;
        }

        $gzipPath = $tmp.'.sql.gz';
        @unlink($tmp);

        try {
            $connection = $this->mysqlConnectionForTenant($tenant);
            $this->executor->assertReady('create', $connection);

            $this->setProgress($backup, 40, 'Dumping tenant database (mysqldump)…');
            $this->executor->dumpToGzipFile($connection, $gzipPath);

            $this->setProgress($backup, 80, 'Storing compressed archive…');
            $path = $this->storagePathFor($tenant, $backup->id);
            $contents = file_get_contents($gzipPath);
            if ($contents === false) {
                throw new \RuntimeException('Could not read dump archive.');
            }

            Storage::disk($this->disk())->put($path, $contents);

            $backup->storage_path = $path;
            $backup->byte_size = strlen($contents);
            $backup->checksum = hash('sha256', $contents);
            $backup->database_name = $connection['database'];
            $backup->status = TenantDatabaseBackup::STATUS_COMPLETED;
            $backup->finished_at = now();
            $backup->error_message = null;
            $backup->progress_percent = 100;
            $backup->progress_message = 'Backup completed successfully';
            $backup->save();
        } catch (Throwable $e) {
            $this->markFailed($backup, $e->getMessage());
        } finally {
            @unlink($gzipPath);
        }
    }

    public function runRestore(
        Tenant $tenant,
        TenantDatabaseBackup $backup,
        ?string $actorUserId,
        ?string $actorEmail,
        ?string $reason,
    ): void {
        $previousOperatorMode = $tenant->operator_access_mode ?? null;

        try {
            $this->setProgress($backup, 15, 'Validating archive and MySQL tools…');
            $this->assertStoragePathOwnedByTenant($tenant, (string) $backup->storage_path);
            $this->assertRestoreArchiveValid($tenant, $backup);

            if ($backup->storage_path === null || ! Storage::disk($this->disk())->exists($backup->storage_path)) {
                throw new \RuntimeException('Backup archive missing from storage.');
            }

            // Soft block workspace traffic during restore.
            $tenant->forceFill(['operator_access_mode' => 'blocked'])->save();

            $tmp = tempnam(sys_get_temp_dir(), 'toweros-tdb-restore-');
            if ($tmp === false) {
                throw new \RuntimeException('Could not allocate temporary restore file.');
            }
            $gzipPath = $tmp.'.sql.gz';
            @unlink($tmp);

            $this->setProgress($backup, 30, 'Loading backup archive…');
            file_put_contents($gzipPath, Storage::disk($this->disk())->get($backup->storage_path));

            try {
                $connection = $this->mysqlConnectionForTenant($tenant);
                if ($backup->database_name !== null && $backup->database_name !== $connection['database']) {
                    throw new \RuntimeException('Backup database name does not match this tenant.');
                }
                $this->executor->assertReady('restore', $connection);

                $this->setProgress($backup, 55, 'Recreating database and importing SQL…');
                $this->executor->restoreFromGzipFile($connection, $gzipPath);
            } finally {
                @unlink($gzipPath);
            }

            $backup->status = TenantDatabaseBackup::STATUS_COMPLETED;
            $backup->finished_at = now();
            $backup->error_message = null;
            $backup->progress_percent = 100;
            $backup->progress_message = 'Restore completed successfully';
            $backup->save();

            $actor = $actorUserId !== null
                ? User::query()->find($actorUserId)
                : null;

            $this->audit->log(
                PlatformTenantAuditEventType::TENANT_BACKUP_RESTORED,
                $tenant,
                $actor,
                null,
                [
                    'backup_id' => $backup->id,
                    'actor_email' => $actorEmail,
                    'reason' => $reason,
                ],
            );
        } catch (Throwable $e) {
            // Keep the dump usable — restore failure must not mark the backup artifact as failed.
            $backup->status = TenantDatabaseBackup::STATUS_COMPLETED;
            $backup->error_message = 'Restore failed: '.Str::limit($e->getMessage(), 1900);
            $backup->progress_percent = 0;
            $backup->progress_message = 'Restore failed';
            $backup->finished_at = now();
            $backup->save();

            throw $e;
        } finally {
            $tenant->refresh();
            $tenant->forceFill([
                'operator_access_mode' => $previousOperatorMode === 'blocked' ? null : $previousOperatorMode,
            ])->save();
        }
    }

    public function markFailed(TenantDatabaseBackup $backup, string $message): void
    {
        $backup->status = TenantDatabaseBackup::STATUS_FAILED;
        $backup->error_message = Str::limit($message, 2000);
        $backup->progress_percent = 0;
        $backup->progress_message = 'Failed';
        $backup->finished_at = now();
        $backup->save();
    }

    public function pruneExpired(): int
    {
        $days = max(1, (int) config('toweros.tenant_database_backup.retention_days', 15));
        $cutoff = now()->subDays($days);
        $pruned = 0;

        TenantDatabaseBackup::query()
            ->where('status', TenantDatabaseBackup::STATUS_COMPLETED)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->chunkById(50, function ($chunk) use (&$pruned): void {
                foreach ($chunk as $backup) {
                    /** @var TenantDatabaseBackup $backup */
                    $this->deleteStorageObject($backup);
                    $backup->status = TenantDatabaseBackup::STATUS_EXPIRED;
                    $backup->storage_path = null;
                    $backup->finished_at = now();
                    $backup->save();
                    $pruned++;
                }
            });

        return $pruned;
    }

    public function scheduleForTenant(Tenant $tenant): ?TenantDatabaseBackup
    {
        $this->assertFeatureEnabled();

        if ($this->hasConcurrentWork($tenant)) {
            return null;
        }

        return $this->queueCreate($tenant, null, 'Scheduled backup', TenantDatabaseBackup::TRIGGER_SCHEDULER);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPayload(TenantDatabaseBackup $backup): array
    {
        return [
            'id' => $backup->id,
            'tenant_id' => $backup->tenant_id,
            'status' => $backup->status,
            'progress_percent' => $backup->progress_percent,
            'progress_message' => $backup->progress_message,
            'name' => $backup->storage_path !== null ? basename($backup->storage_path) : ('backup-'.$backup->id),
            'storage_path' => $backup->storage_path,
            'byte_size' => $backup->byte_size,
            'checksum' => $backup->checksum,
            'database_name' => $backup->database_name,
            'triggered_by' => $backup->triggered_by,
            'actor_email' => $backup->actor_email,
            'reason' => $backup->reason,
            'error_message' => $backup->error_message,
            'started_at' => $backup->started_at?->toIso8601String(),
            'finished_at' => $backup->finished_at?->toIso8601String(),
            'created_at' => $backup->created_at?->toIso8601String(),
            'updated_at' => $backup->updated_at?->toIso8601String(),
        ];
    }

    private function setProgress(TenantDatabaseBackup $backup, int $percent, string $message): void
    {
        $backup->progress_percent = max(0, min(100, $percent));
        $backup->progress_message = Str::limit($message, 250);
        $backup->save();
    }

    private function assertRestoreArchiveValid(Tenant $tenant, TenantDatabaseBackup $backup): void
    {
        if (! $backup->isCompleted() && $backup->status !== TenantDatabaseBackup::STATUS_RESTORING) {
            throw ValidationException::withMessages([
                'backup' => [__('Only completed backups can be restored.')],
            ]);
        }

        if ($backup->storage_path === null || $backup->storage_path === '') {
            throw ValidationException::withMessages([
                'backup' => [__('Backup archive path is missing.')],
            ]);
        }

        $this->assertStoragePathOwnedByTenant($tenant, $backup->storage_path);

        $disk = Storage::disk($this->disk());
        if (! $disk->exists($backup->storage_path)) {
            throw ValidationException::withMessages([
                'backup' => [__('Backup archive is missing from storage.')],
            ]);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'toweros-tdb-validate-');
        if ($tmp === false) {
            throw ValidationException::withMessages([
                'backup' => [__('Could not allocate temporary file for validation.')],
            ]);
        }
        $tmpGz = $tmp.'.sql.gz';
        @unlink($tmp);

        try {
            file_put_contents($tmpGz, $disk->get($backup->storage_path));
            $this->assertLooksLikeMysqlDump($tmpGz);
        } finally {
            @unlink($tmpGz);
        }

        $expectedDb = $tenant->database()->getName();
        if (
            is_string($backup->database_name)
            && $backup->database_name !== ''
            && $expectedDb !== ''
            && $backup->database_name !== $expectedDb
        ) {
            throw ValidationException::withMessages([
                'backup' => [__('This backup belongs to database :from, but this tenant uses :to.', [
                    'from' => $backup->database_name,
                    'to' => $expectedDb,
                ])],
            ]);
        }
    }

    public function assertStoragePathOwnedByTenant(Tenant $tenant, string $path): void
    {
        $prefix = (string) $tenant->id.'/backups/';
        if ($path === '' || ! str_starts_with($path, $prefix)) {
            throw ValidationException::withMessages([
                'backup' => [__('Invalid backup storage path for this tenant.')],
            ]);
        }

        if (str_contains($path, '..')) {
            throw ValidationException::withMessages([
                'backup' => [__('Invalid backup storage path.')],
            ]);
        }
    }

    private function assertNoConcurrentWork(Tenant $tenant, ?string $exceptBackupId = null): void
    {
        if ($this->hasConcurrentWork($tenant, $exceptBackupId)) {
            throw ValidationException::withMessages([
                'backup' => [__('A backup or restore is already in progress for this tenant.')],
            ]);
        }
    }

    private function hasConcurrentWork(Tenant $tenant, ?string $exceptBackupId = null): bool
    {
        $max = max(1, (int) config('toweros.tenant_database_backup.max_concurrent_per_tenant', 1));
        $query = TenantDatabaseBackup::query()
            ->where('tenant_id', (string) $tenant->id)
            ->whereIn('status', [
                TenantDatabaseBackup::STATUS_PENDING,
                TenantDatabaseBackup::STATUS_RUNNING,
                TenantDatabaseBackup::STATUS_RESTORING,
            ]);

        if ($exceptBackupId !== null) {
            $query->where('id', '!=', $exceptBackupId);
        }

        return $query->count() >= $max;
    }

    private function storagePathFor(Tenant $tenant, string $backupId): string
    {
        $now = now();

        return sprintf(
            '%s/backups/%s/%s/%s.sql.gz',
            (string) $tenant->id,
            $now->format('Y'),
            $now->format('m'),
            $backupId,
        );
    }

    private function disk(): string
    {
        return (string) config('toweros.tenant_files.disk', 'tenant_files');
    }

    private function deleteStorageObject(TenantDatabaseBackup $backup): void
    {
        if ($backup->storage_path === null || $backup->storage_path === '') {
            return;
        }

        try {
            Storage::disk($this->disk())->delete($backup->storage_path);
        } catch (Throwable) {
            // Best-effort delete; metadata still purged.
        }
    }

    private function normalizeUploadedGzipToFile(string $sourcePath, string $targetGzipPath): void
    {
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            throw ValidationException::withMessages([
                'file' => [__('Uploaded file could not be read.')],
            ]);
        }

        $head = file_get_contents($sourcePath, false, null, 0, 2);
        if ($head !== "\x1f\x8b") {
            throw ValidationException::withMessages([
                'file' => [__('File is not a valid gzip archive (.sql.gz).')],
            ]);
        }

        if (! @copy($sourcePath, $targetGzipPath)) {
            throw ValidationException::withMessages([
                'file' => [__('Could not stage uploaded gzip backup.')],
            ]);
        }
    }

    private function gzipPlainSqlFile(string $sourcePath, string $targetGzipPath): void
    {
        if ($sourcePath === '' || ! is_file($sourcePath)) {
            throw ValidationException::withMessages([
                'file' => [__('Uploaded file could not be read.')],
            ]);
        }

        $in = fopen($sourcePath, 'rb');
        $out = gzopen($targetGzipPath, 'wb9');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            throw ValidationException::withMessages([
                'file' => [__('Could not compress uploaded SQL backup.')],
            ]);
        }

        try {
            while (! feof($in)) {
                $chunk = fread($in, 1024 * 1024);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                gzwrite($out, $chunk);
            }
        } finally {
            fclose($in);
            gzclose($out);
        }
    }

    private function assertLooksLikeMysqlDump(string $gzipPath): void
    {
        $gz = gzopen($gzipPath, 'rb');
        if ($gz === false) {
            throw ValidationException::withMessages([
                'file' => [__('Could not open uploaded backup for validation.')],
            ]);
        }

        try {
            $sample = (string) gzread($gz, 8192);
        } finally {
            gzclose($gz);
        }

        $sampleLower = strtolower($sample);
        $ok = str_contains($sampleLower, 'mysqldump')
            || str_contains($sampleLower, 'mariadb dump')
            || str_contains($sampleLower, 'create table')
            || str_contains($sampleLower, 'insert into')
            || str_contains($sampleLower, '-- dump');

        if (! $ok) {
            throw ValidationException::withMessages([
                'file' => [__('File does not look like a MySQL/MariaDB logical dump.')],
            ]);
        }
    }

    /**
     * @return array{host: string, port: int|string, username: string, password: string, database: string}
     */
    private function mysqlConnectionForTenant(Tenant $tenant): array
    {
        $name = $tenant->database()->getName();
        if (! is_string($name) || $name === '') {
            throw new \RuntimeException('Tenant database name is not configured.');
        }

        $central = config('database.connections.central');
        if (! is_array($central)) {
            throw new \RuntimeException('Central database connection is not configured.');
        }

        return [
            'host' => (string) ($central['host'] ?? '127.0.0.1'),
            'port' => $central['port'] ?? 3306,
            'username' => (string) ($central['username'] ?? ''),
            'password' => (string) ($central['password'] ?? ''),
            'database' => $name,
        ];
    }
}
