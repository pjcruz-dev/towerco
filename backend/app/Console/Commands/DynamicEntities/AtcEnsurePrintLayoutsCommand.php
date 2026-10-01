<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\DynPrintLayoutService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class AtcEnsurePrintLayoutsCommand extends Command
{
    protected $signature = 'atc:ensure-print-layouts
        {--tenant= : Tenant UUID}
        {--all : Run for every tenant}';

    protected $description = 'Seed dynamic print settings and field groups (e.g. Site Permits → Permit Transmittal, BIR 2307)';

    public function handle(DynPrintLayoutService $service): int
    {
        $tenants = $this->resolveTenants();
        if ($tenants->isEmpty()) {
            $this->error('No tenants matched. Pass --tenant=<uuid> or --all.');

            return self::FAILURE;
        }

        $failed = 0;
        foreach ($tenants as $tenant) {
            $domain = (string) ($tenant->domains()->first()?->domain ?? '');
            $env = (string) ($tenant->environment ?? '');
            $label = trim(implode(' · ', array_filter([
                (string) ($tenant->slug ?: $tenant->id),
                $env !== '' ? $env : null,
                $domain !== '' ? $domain : null,
            ])));
            $this->line("→ {$label}");
            $this->line('  id='.$tenant->id);

            tenancy()->initialize($tenant);
            try {
                $n = $service->ensureAllKnown();
                if ($n === 0) {
                    $this->warn('  No active dynamic entities in this tenant (skip / import DE pack first).');
                } else {
                    $this->info("  Ensured print layouts for {$n} entit(y/ies).");
                }
            } catch (\Throwable $e) {
                $failed++;
                $this->error('  '.$e->getMessage());
            } finally {
                tenancy()->end();
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function resolveTenants(): Collection
    {
        if ($this->option('all')) {
            return Tenant::query()->orderBy('created_at')->get();
        }

        $tenantId = (string) ($this->option('tenant') ?: '');
        if ($tenantId === '') {
            return collect();
        }

        $tenant = Tenant::query()->find($tenantId);

        return $tenant !== null ? collect([$tenant]) : collect();
    }
}
