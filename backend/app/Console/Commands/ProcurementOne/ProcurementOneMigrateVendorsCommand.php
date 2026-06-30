<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementVendorMigrationService;
use Illuminate\Console\Command;

final class ProcurementOneMigrateVendorsCommand extends Command
{
    protected $signature = 'procurement-one:migrate-vendors
        {--tenant= : Tenant id or domain}
        {--all : Run for all tenants}';

    protected $description = 'Sync procurement_vendors from E-Approval master data vendors';

    public function handle(ProcurementVendorMigrationService $migration): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenants matched.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $result = $migration->migrateFromMasterData();
            $this->line(sprintf(
                '[%s] vendors migrated: %d created, %d updated (%d total)',
                $tenant->domains->first()?->domain ?? $tenant->id,
                $result['created'],
                $result['updated'],
                $result['total'],
            ));
            tenancy()->end();
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Tenant>
     */
    private function resolveTenants(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->with('domains')->get()->all();
        }

        $needle = trim((string) $this->option('tenant'));
        if ($needle === '') {
            $this->error('Provide --tenant=<id|domain> or --all');

            return [];
        }

        $tenant = Tenant::query()
            ->where('id', $needle)
            ->orWhereHas('domains', static fn ($q) => $q->where('domain', $needle))
            ->with('domains')
            ->first();

        return $tenant instanceof Tenant ? [$tenant] : [];
    }
}
