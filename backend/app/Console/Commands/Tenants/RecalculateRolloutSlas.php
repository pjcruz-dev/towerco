<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\Rollout\Services\RolloutSlaRecalculationService;
use Illuminate\Console\Command;

class RecalculateRolloutSlas extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'tenants:recalculate-rollout-slas
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
    ';

    protected $description = 'Recalculate rollout SLA target dates using current holiday calendar (region-aware).';

    public function handle(RolloutSlaRecalculationService $service): int
    {
        $tenant = $this->resolveTenantFromOptions();

        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $count = $tenant->run(static fn () => $service->recalculateActivePrograms());

        $this->info("Recalculated SLA dates for {$count} active rollout(s) on tenant [{$tenant->id}].");

        return self::SUCCESS;
    }
}
