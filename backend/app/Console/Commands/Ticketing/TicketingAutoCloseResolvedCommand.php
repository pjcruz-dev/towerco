<?php

declare(strict_types=1);

namespace App\Console\Commands\Ticketing;

use App\Models\Tenant;
use App\Modules\Ticketing\Services\TicketingAutoCloseService;
use Illuminate\Console\Command;

class TicketingAutoCloseResolvedCommand extends Command
{
    protected $signature = 'ticketing:auto-close-resolved
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Auto-close Ticketing tickets that have been resolved past the grace window (default 3 days).';

    public function handle(TicketingAutoCloseService $autoClose): int
    {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $totalClosed = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($autoClose, $tenant, &$totalClosed): void {
                $result = $autoClose->run();
                $totalClosed += $result['closed'];

                if ($result['closed'] > 0) {
                    $this->line(sprintf(
                        'Tenant %s: auto-closed %d ticket(s) (grace %d day(s)).',
                        $tenant->id,
                        $result['closed'],
                        $result['days'],
                    ));
                }
            });
        }

        $this->info("Ticketing auto-close complete. {$totalClosed} ticket(s) closed.");

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
