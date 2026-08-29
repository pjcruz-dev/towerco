<?php

declare(strict_types=1);

namespace App\Modules\Platform\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Models\TenantDatabaseBackup;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Support\Facades\Log;

final class RestoreTenantDatabaseBackupJob extends AbstractQueuedJob
{
    public int $tries = 1;

    public function __construct(
        public readonly string $backupId,
        public readonly string $tenantId,
        public readonly ?string $actorUserId = null,
        public readonly ?string $actorEmail = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct();
        $this->onQueue(config('toweros.queues.tenant', config('toweros.queues.default')));
        $this->timeout = max(60, (int) config('toweros.tenant_database_backup.job_timeout_seconds', 1800));
    }

    public function handle(TenantDatabaseBackupService $service): void
    {
        $backup = TenantDatabaseBackup::query()->find($this->backupId);
        if ($backup === null || $backup->tenant_id !== $this->tenantId) {
            Log::warning('tenant_database_backup.restore_missing', [
                'backup_id' => $this->backupId,
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            Log::warning('tenant_database_backup.restore_tenant_missing', [
                'backup_id' => $this->backupId,
                'tenant_id' => $this->tenantId,
            ]);
            $backup->status = TenantDatabaseBackup::STATUS_COMPLETED;
            $backup->error_message = 'Restore failed: tenant no longer exists.';
            $backup->finished_at = now();
            $backup->save();

            return;
        }

        $service->runRestore($tenant, $backup, $this->actorUserId, $this->actorEmail, $this->reason);
    }
}
