<?php

declare(strict_types=1);

namespace App\Console\Commands\Rollout;

use App\Models\Tenant;
use App\Modules\Rollout\Services\RolloutSlaAtRiskService;
use Illuminate\Console\Command;

class RecomputeRolloutSlaRiskCommand extends Command
{
    protected $signature = 'rollout:recompute-sla-risk
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Recompute denormalized rollout SLA remaining working days for tenant(s).';

    public function handle(RolloutSlaAtRiskService $service): int
    {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $total = 0;

        foreach ($tenantIds as $tenantId) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($service, $tenant, &$total): void {
                $updated = $service->recomputeAll();
                $total += $updated;

                if ($updated > 0) {
                    $this->line("Tenant {$tenant->id}: refreshed {$updated} rollout(s).");
                }
            });
        }

        $this->info("Rollout SLA risk recompute complete. {$total} rollout(s) refreshed.");

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveTenantIds(): array
    {
        $explicit = array_values(array_filter((array) $this->option('tenants'), static fn ($id) => is_string($id) && $id !== ''));
        if ($explicit !== []) {
            return $explicit;
        }

        $domain = (string) ($this->option('domain') ?: '');
        if ($domain !== '') {
            $tenant = Tenant::query()->whereHas('domains', static fn ($q) => $q->where('domain', $domain))->first();

            return $tenant ? [(string) $tenant->id] : [];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }
}
