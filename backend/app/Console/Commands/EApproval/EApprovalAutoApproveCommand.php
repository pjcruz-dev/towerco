<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Models\Tenant;
use App\Modules\EApproval\Services\EApprovalAutoApproveService;
use Illuminate\Console\Command;

class EApprovalAutoApproveCommand extends Command
{
    protected $signature = 'e-approval:auto-approve
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Auto-approve pending E-Form steps whose day count has elapsed.';

    public function handle(EApprovalAutoApproveService $runner): int
    {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $total = 0;
        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($runner, $tenant, &$total): void {
                $count = $runner->run();
                $total += $count;
                if ($count > 0) {
                    $this->line(sprintf('Tenant %s: %d auto-approval(s).', $tenant->id, $count));
                }
            });
        }

        $this->info("Auto-approve complete. {$total} approval(s).");

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
