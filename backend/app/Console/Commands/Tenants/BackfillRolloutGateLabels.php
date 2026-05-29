<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\Rollout\Services\RolloutPhaseGateLabelBackfillService;
use Illuminate\Console\Command;

class BackfillRolloutGateLabels extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'tenants:backfill-rollout-gates
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
    ';

    protected $description = 'Backfill rollout timeline gate labels from assigned playbook snapshot.';

    public function handle(RolloutPhaseGateLabelBackfillService $service): int
    {
        $tenant = $this->resolveTenantFromOptions();

        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $domain = $tenant->domains()->value('domain') ?? $tenant->id;
        $updated = $tenant->run(static fn () => $service->backfillAll());

        $this->info("Updated {$updated} phase gate label(s) for {$domain}.");

        return self::SUCCESS;
    }
}
