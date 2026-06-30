<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementOnePurgeTransactionalDataService;
use Illuminate\Console\Command;

final class ProcurementOnePurgeTransactionalDataCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'procurement-one:purge-transactional-data
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain hostname}
        {--all : Run for every tenant}
        {--dry-run : Preview row counts without deleting}
        {--force : Required to perform destructive deletes}
        {--keep-e-approval-submissions : Keep PR/PO/AP E-Approval submissions}
        {--with-vendors : Also delete vendor master records}
        {--with-budget : Also delete cost centers and budget lines}
        {--with-inventory-locations : Also delete inventory location master data}
        {--with-vendor-registration-submissions : Also delete vendor registration E-Approval submissions}
        {--keep-numbering : Do not reset procurement / E-Approval document sequences}
    ';

    protected $description = 'Delete procurement transactional data (PR, PO, RFQ, GRN, AP, payments, contracts) for a clean form rollout.';

    public function handle(ProcurementOnePurgeTransactionalDataService $purge): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenants matched. Pass --tenant=UUID, --domain=hostname, or --all.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        if (! $dryRun && ! $force) {
            $this->error('Refusing to delete data without --force. Use --dry-run to preview counts first.');

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('Dry run — no database writes will be performed.');
        } elseif ($this->input->isInteractive() && ! $this->confirm('This permanently deletes procurement transactional data. Continue?', false)) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $options = [
            'purgeEApprovalSubmissions' => ! (bool) $this->option('keep-e-approval-submissions'),
            'purgeVendors' => (bool) $this->option('with-vendors'),
            'purgeBudget' => (bool) $this->option('with-budget'),
            'purgeInventoryLocations' => (bool) $this->option('with-inventory-locations'),
            'includeVendorRegistrationSubmissions' => (bool) $this->option('with-vendor-registration-submissions'),
            'resetNumbering' => ! (bool) $this->option('keep-numbering'),
        ];

        foreach ($tenants as $tenant) {
            $domain = $tenant->domains()->value('domain') ?? $tenant->id;

            $counts = $tenant->run(function () use ($purge, $dryRun, $options): array {
                return $purge->purge(
                    $dryRun,
                    $options['purgeEApprovalSubmissions'],
                    $options['purgeVendors'],
                    $options['purgeBudget'],
                    $options['purgeInventoryLocations'],
                    $options['includeVendorRegistrationSubmissions'],
                    $options['resetNumbering'],
                );
            });

            $this->line(sprintf('[%s] purge summary%s:', $domain, $dryRun ? ' [dry-run]' : ''));
            ksort($counts);
            foreach ($counts as $table => $count) {
                if ($count <= 0) {
                    continue;
                }
                $this->line(sprintf('  - %s: %d', $table, $count));
            }
        }

        $this->info($dryRun
            ? 'Dry run complete. Re-run with --force to delete data.'
            : 'Procurement transactional purge complete. Forms and settings were kept.');

        return self::SUCCESS;
    }

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('id')->get()->all();
        }

        $tenant = $this->resolveTenantFromOptions();

        return $tenant instanceof Tenant ? [$tenant] : [];
    }
}
