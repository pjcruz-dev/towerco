<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Models\Tenant;
use App\Modules\Rollout\Services\TenantPublicHolidayService;
use Illuminate\Console\Command;

class SeedTenantPublicHolidays extends Command
{
    protected $signature = 'tenants:seed-holidays
        {--domain= : Tenant domain}
        {--year= : Calendar year (default: current year)}
    ';

    protected $description = 'Seed Philippines public holidays into tenant DB for SLA working-day math.';

    public function handle(TenantPublicHolidayService $service): int
    {
        $domain = (string) ($this->option('domain') ?: config('toweros.demo.tenant_domain', 'alliance.localhost'));
        $year = (int) ($this->option('year') ?: now()->format('Y'));

        $tenant = Tenant::query()
            ->whereHas('domains', static fn ($q) => $q->where('domain', $domain))
            ->first();

        if ($tenant === null) {
            $this->error("Tenant not found for domain [{$domain}].");

            return self::FAILURE;
        }

        $count = $tenant->run(static fn () => $service->seedPhilippinesYear($year));

        $this->info("Seeded {$count} PH holidays for {$domain} ({$year}).");

        return self::SUCCESS;
    }
}
