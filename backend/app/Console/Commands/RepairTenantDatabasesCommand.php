<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Audit tenant rows in the central DB vs actual MySQL tenant databases.
 */
final class RepairTenantDatabasesCommand extends Command
{
    protected $signature = 'toweros:repair-tenant-databases
        {--create : Create missing MySQL databases and run tenants:migrate}
        {--delete-orphans : Remove central tenant rows whose database does not exist (destructive)}
        {--tenant= : Limit to one tenant UUID}
        {--force : Skip confirmation for --delete-orphans}';

    protected $description = 'List tenants whose MySQL database is missing; optionally create DBs or remove orphan tenant rows.';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $query = Tenant::query()->with('domains')->orderBy('created_at');
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('id', $tenantId);
        }

        $tenants = $query->get();
        if ($tenants->isEmpty()) {
            $this->info('No tenants found.');

            return self::SUCCESS;
        }

        $missing = [];
        $present = [];

        foreach ($tenants as $tenant) {
            $database = $tenant->database()->getName();
            $exists = $tenant->database()->manager()->databaseExists($database);
            if ($exists) {
                $present[] = [$tenant->id, $database, $tenant->domains->pluck('domain')->implode(', ') ?: '—'];
            } else {
                $missing[] = [$tenant->id, $database, $tenant->domains->pluck('domain')->implode(', ') ?: '—'];
            }
        }

        if ($present !== []) {
            $this->info('Tenants with databases:');
            $this->table(['Tenant ID', 'Database', 'Domains'], $present);
        }

        if ($missing === []) {
            $this->info('All tenant databases exist.');

            return self::SUCCESS;
        }

        $this->warn('Tenants missing MySQL database:');
        $this->table(['Tenant ID', 'Expected database', 'Domains'], $missing);

        if ($this->option('delete-orphans')) {
            return $this->deleteOrphans($missing);
        }

        if ($this->option('create')) {
            return $this->createMissing(array_column($missing, 0));
        }

        $this->line('');
        $this->line('Next steps:');
        $this->line('  Create DB + migrate:  php artisan toweros:repair-tenant-databases --create');
        $this->line('  Remove orphan rows:   php artisan toweros:repair-tenant-databases --delete-orphans');

        return self::FAILURE;
    }

    /**
     * @param  list<string>  $tenantIds
     */
    private function createMissing(array $tenantIds): int
    {
        foreach ($tenantIds as $id) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($id);
            if ($tenant === null) {
                continue;
            }

            $database = $tenant->database()->getName();
            $this->info("Creating database {$database}…");

            $tenant->database()->manager()->createDatabase($tenant);

            $exit = Artisan::call('tenants:migrate', [
                '--force' => true,
                '--tenants' => [$tenant->id],
            ]);

            if ($exit !== self::SUCCESS) {
                $this->error("Migration failed for tenant {$tenant->id}.");

                return self::FAILURE;
            }

            $this->info("Migrated tenant {$tenant->id}.");
        }

        $this->info('Repair complete.');

        return self::SUCCESS;
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $missing
     */
    private function deleteOrphans(array $missing): int
    {
        if (! $this->option('force') && ! $this->confirm('Delete '.count($missing).' tenant row(s) from central DB? This cannot be undone.', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        foreach ($missing as [$id]) {
            $this->call('tenants:delete', [
                'tenant' => $id,
                '--force' => true,
            ]);
        }

        $this->info('Orphan tenant rows removed.');

        return self::SUCCESS;
    }
}
