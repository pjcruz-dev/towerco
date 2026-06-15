<?php

declare(strict_types=1);

namespace App\Console\Commands\Tenants;

use App\Console\Commands\Tenants\Concerns\ResolvesTenantFromConsoleOptions;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Update central tenants.plan_tier (E-Approval file uploads require professional or enterprise).
 */
final class SetTenantPlanTier extends Command
{
    use ResolvesTenantFromConsoleOptions;

    protected $signature = 'tenants:set-plan-tier
        {tier : starter, professional, or enterprise}
        {--domain= : Tenant hostname (e.g. atc.localhost)}
        {--tenant= : Tenant UUID}
        {--all : Apply to every tenant in central DB}
    ';

    protected $description = 'Set billing plan tier on one or all tenants (enables E-Approval file fields on professional+).';

    public function handle(): int
    {
        $tier = strtolower(trim((string) $this->argument('tier')));
        if (! in_array($tier, ['starter', 'professional', 'enterprise'], true)) {
            $this->error('Tier must be starter, professional, or enterprise.');

            return self::FAILURE;
        }

        $tenants = $this->resolveTargets();
        if ($tenants === []) {
            $this->error('No tenant found. Use --domain=atc.localhost or --all.');

            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $tenant->plan_tier = $tier;
            $tenant->save();
            $label = $tenant->domains()->first()?->domain ?? $tenant->id;
            $this->info("Plan tier set to {$tier} → {$label} ({$tenant->id})");
        }

        return self::SUCCESS;
    }

    /**
     * @return list<Tenant>
     */
    private function resolveTargets(): array
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('created_at')->get()->all();
        }

        $tenant = $this->resolveTenantFromOptions();
        if ($tenant === null) {
            return [];
        }

        return [$tenant];
    }
}
