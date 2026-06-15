<?php

declare(strict_types=1);

namespace App\Console\Commands\Ticketing;

use App\Models\Tenant;
use App\Modules\Ticketing\Services\TicketingSlaRunnerService;
use Illuminate\Console\Command;

class TicketingSlaRunCommand extends Command
{
    protected $signature = 'ticketing:sla-run
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Run Ticketing SLA reminders and escalations for tenant(s).';

    public function handle(TicketingSlaRunnerService $runner): int
    {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $totalReminders = 0;
        $totalEscalations = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($runner, $tenant, &$totalReminders, &$totalEscalations): void {
                $result = $runner->run();
                $totalReminders += $result['reminders'];
                $totalEscalations += $result['escalations'];

                if ($result['reminders'] > 0 || $result['escalations'] > 0) {
                    $this->line(sprintf(
                        'Tenant %s: %d reminder(s), %d escalation(s).',
                        $tenant->id,
                        $result['reminders'],
                        $result['escalations'],
                    ));
                }
            });
        }

        $this->info("Ticketing SLA run complete. {$totalReminders} reminder(s), {$totalEscalations} escalation(s).");

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
