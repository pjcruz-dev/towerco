<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\ManagedSeriesService;
use Illuminate\Console\Command;

final class SeedNumberSeriesCommand extends Command
{
    protected $signature = 'dyn:seed-number-series {--tenant= : Tenant UUID}';

    protected $description = 'Add the ticket and asset rows to Document Series';

    public function handle(ManagedSeriesService $series): int
    {
        $tenantId = (string) ($this->option('tenant') ?: '');
        if ($tenantId === '') {
            $this->error('Provide --tenant=<uuid>.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        tenancy()->initialize($tenant);

        try {
            $series->ensureTicket();
            $series->ensureAsset();
            $this->info('Done. Ticket and asset rows are on Document Series.');

            return self::SUCCESS;
        } finally {
            tenancy()->end();
        }
    }
}
