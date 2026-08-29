<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenancy;

use App\Models\Tenant;
use App\Modules\Platform\Services\TenantDatabaseBackup\TenantDatabaseBackupService;
use Illuminate\Console\Command;

final class TenantsBackupScheduleCommand extends Command
{
    protected $signature = 'tenants:backup-schedule
                            {--tenant= : Limit to a single tenant UUID}
                            {--force : Run even when schedule_enabled is false}';

    protected $description = 'Queue logical MySQL backups for active tenants (Data Protection Center).';

    public function handle(TenantDatabaseBackupService $backups): int
    {
        if (! (bool) config('toweros.tenant_database_backup.enabled', true)) {
            $this->warn('Tenant database backups are disabled (TOWEROS_TENANT_DB_BACKUP_ENABLED).');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! (bool) config('toweros.tenant_database_backup.schedule_enabled', false)) {
            $this->info('Scheduled backups are disabled. Pass --force to queue anyway.');

            return self::SUCCESS;
        }

        $query = Tenant::query()->orderBy('created_at');
        $tenantId = $this->option('tenant');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->whereKey($tenantId);
        }

        $queued = 0;
        $skipped = 0;

        $query->each(function (Tenant $tenant) use ($backups, &$queued, &$skipped): void {
            $backup = $backups->scheduleForTenant($tenant);
            if ($backup === null) {
                $skipped++;
                $this->line("Skipped {$tenant->id} (in progress or unavailable).");

                return;
            }

            $queued++;
            $this->info("Queued backup {$backup->id} for tenant {$tenant->id}.");
        });

        $this->info("Done. Queued={$queued}, skipped={$skipped}.");

        return self::SUCCESS;
    }
}
