<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenancy;

use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Console\Command;

final class TenantsBackupPruneCommand extends Command
{
    protected $signature = 'tenants:backup-prune';

    protected $description = 'Expire tenant database backups older than retention_days and delete storage objects.';

    public function handle(TenantDatabaseBackupService $backups): int
    {
        $pruned = $backups->pruneExpired();
        $this->info("Pruned {$pruned} expired tenant database backup(s).");

        return self::SUCCESS;
    }
}
