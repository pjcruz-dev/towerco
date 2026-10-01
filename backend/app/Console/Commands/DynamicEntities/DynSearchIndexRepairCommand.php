<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Modules\DynamicEntities\Services\DynSearchIndexService;
use Illuminate\Console\Command;

/**
 * Tenant-context search index repair (used by Manage Automation & Cron Jobs).
 */
final class DynSearchIndexRepairCommand extends Command
{
    protected $signature = 'dyn:search-index-repair';

    protected $description = 'Repair dyn record search/filter indexes for the current tenant.';

    public function handle(DynSearchIndexService $service): int
    {
        if (! tenant()) {
            $this->error('This command must run inside a tenant context.');

            return self::FAILURE;
        }

        $result = $service->repair();
        $this->info('Search index repair complete: '.json_encode($result));

        return self::SUCCESS;
    }
}
