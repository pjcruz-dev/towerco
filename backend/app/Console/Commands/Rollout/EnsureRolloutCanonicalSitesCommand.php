<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Modules\Rollout\Models\RolloutProgram;
use App\Modules\Rollout\Services\RolloutCanonicalSiteService;
use Illuminate\Console\Command;

class EnsureRolloutCanonicalSitesCommand extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'rollouts:ensure-canonical-sites
        {--tenant= : Tenant UUID}
        {--domain= : Tenant domain}
    ';

    protected $description = 'Issue TCO site IDs and create canonical site registry rows for rollouts missing them.';

    public function handle(RolloutCanonicalSiteService $canonicalSites): int
    {
        $tenant = $this->resolveTenantFromOptions();

        if ($tenant === null) {
            $this->error('Tenant not found. Pass --tenant=UUID or --domain=hostname.');

            return self::FAILURE;
        }

        $provisioned = $tenant->run(static function () use ($canonicalSites): int {
            $count = 0;

            RolloutProgram::query()
                ->where('status', '!=', 'batch')
                ->where(static function ($query): void {
                    $query->whereNull('tco_site_id')
                        ->orWhereNull('site_id');
                })
                ->orderBy('created_at')
                ->each(static function (RolloutProgram $program) use ($canonicalSites, &$count): void {
                    $site = $canonicalSites->ensureForProgram($program);
                    if ($site !== null) {
                        $count++;
                    }
                });

            return $count;
        });

        $this->info("Provisioned canonical sites for {$provisioned} rollout(s) on tenant [{$tenant->id}].");

        return self::SUCCESS;
    }
}
