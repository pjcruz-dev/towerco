<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Models\Tenant;
use App\Modules\EApproval\Services\EApprovalSlaRunnerService;
use Illuminate\Console\Command;

class EApprovalDailyFollowUpCommand extends Command
{
    protected $signature = 'e-approval:daily-follow-up
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Send one daily reminder to each pending reviewer and approver.';

    public function handle(EApprovalSlaRunnerService $runner): int
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
                $result = $runner->runDailyFollowUp();
                $total += $result['reminders'];
                if ($result['reminders'] > 0) {
                    $this->line(sprintf('Tenant %s: %d daily reminder(s).', $tenant->id, $result['reminders']));
                }
            });
        }

        $this->info("Daily follow-up complete. {$total} reminder(s).");

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
