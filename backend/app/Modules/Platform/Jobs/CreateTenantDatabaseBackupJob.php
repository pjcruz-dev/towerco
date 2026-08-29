<?php

declare(strict_types=1);

namespace App\Modules\Platform\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Models\TenantDatabaseBackup;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Support\Facades\Log;

final class CreateTenantDatabaseBackupJob extends AbstractQueuedJob
{
    public int $tries = 2;

    public function __construct(
        public readonly string $backupId,
    ) {
        parent::__construct();
        $this->onQueue(config('toweros.queues.tenant', config('toweros.queues.default')));
        $this->timeout = max(60, (int) config('toweros.tenant_database_backup.job_timeout_seconds', 1800));
    }

    public function handle(TenantDatabaseBackupService $service): void
    {
        $backup = TenantDatabaseBackup::query()->find($this->backupId);
        if ($backup === null) {
            Log::warning('tenant_database_backup.missing', ['backup_id' => $this->backupId]);

            return;
        }

        $tenant = Tenant::query()->find($backup->tenant_id);
        if ($tenant === null) {
            $service->markFailed($backup, 'Tenant no longer exists.');

            return;
        }

        $service->runCreate($tenant, $backup);
    }
}
