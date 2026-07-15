<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementPrMigrationService;
use Illuminate\Console\Command;

final class ProcurementOneMigratePrsCommand extends Command
{
    protected $signature = 'procurement-one:migrate-prs {--tenant=} {--all}';

    protected $description = 'Import purchase requisitions from E-Approval submissions into Procurement-One';

    public function handle(ProcurementPrMigrationService $migration): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants === []) {
            $this->error('No tenants matched.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            tenancy()->initialize($tenant);
            $result = $migration->migrateFromEApprovalSubmissions();
            tenancy()->end();

            $domain = $tenant->domains()->value('domain') ?? $tenant->id;
            $this->line(sprintf(
                '[%s] PRs migrated: %d created, %d updated (%d total)',
                $domain,
                $result['created'],
                $result['updated'],
                $result['total'],
            ));
        }

        return self::SUCCESS;
    }

    /** @return list<Tenant> */
    private function resolveTenants(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->get()->all();
        }

        $tenantId = trim((string) $this->option('tenant'));
        if ($tenantId === '') {
            $this->error('Provide --tenant=<id> or --all');

            return [];
        }

        $tenant = Tenant::query()->find($tenantId);

        return $tenant instanceof Tenant ? [$tenant] : [];
    }
}
