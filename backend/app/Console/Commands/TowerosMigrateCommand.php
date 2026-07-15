<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Run central + all tenant migrations (use after pulling schema changes).
 */
final class TowerosMigrateCommand extends Command
{
    protected $signature = 'toweros:migrate
        {--central-only : Run only central (landlord) migrations}
        {--tenants-only : Run only tenant migrations across all tenants}
        {--seed : Pass --seed to tenant migrations (optional demo data)}
    ';

    protected $description = 'Run central database migrations, then migrate all tenant databases.';

    public function handle(): int
    {
        $centralOnly = (bool) $this->option('central-only');
        $tenantsOnly = (bool) $this->option('tenants-only');

        if (! $tenantsOnly) {
            $this->info('Migrating central database…');
            $centralExit = $this->call('migrate', ['--force' => true]);
            if ($centralExit !== self::SUCCESS) {
                $this->error('Central migration failed.');

                return self::FAILURE;
            }
        }

        if (! $centralOnly) {
            $this->info('Migrating all tenant databases…');
            $params = ['--force' => true];
            if ($this->option('seed')) {
                $params['--seed'] = true;
            }

            $tenantExit = $this->migrateExistingTenantDatabases($params);
            if ($tenantExit !== self::SUCCESS) {
                $this->error('Tenant migration failed.');

                return self::FAILURE;
            }

            $this->info('Ensuring tenant RBAC baselines…');
            $rbacExit = $this->call('tenants:ensure-rbac');
            if ($rbacExit !== self::SUCCESS) {
                $this->error('Tenant RBAC baseline sync failed.');

                return self::FAILURE;
            }
        }

        $this->info('TowerOS migrations complete.');

        return self::SUCCESS;
    }

    /**
     * Migrate only tenants whose MySQL database already exists (skip orphan central rows).
     *
     * @param  array<string, mixed>  $params
     */
    private function migrateExistingTenantDatabases(array $params): int
    {
        $migrated = 0;
        $skipped = 0;

        foreach (Tenant::query()->orderBy('created_at')->cursor() as $tenant) {
            $database = $tenant->database()->getName();
            if (! $tenant->database()->manager()->databaseExists($database)) {
                $this->warn("Skipping {$tenant->id}: database {$database} does not exist.");
                $skipped++;

                continue;
            }

            $this->line("Migrating tenant {$tenant->id}…");
            $exit = Artisan::call('tenants:migrate', array_merge($params, [
                '--tenants' => [$tenant->id],
            ]));

            if ($exit !== self::SUCCESS) {
                return self::FAILURE;
            }

            $migrated++;
        }

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} tenant(s) with no database. Run: php artisan toweros:repair-tenant-databases");
        }

        $this->info("Migrated {$migrated} tenant database(s).");

        return self::SUCCESS;
    }
}
