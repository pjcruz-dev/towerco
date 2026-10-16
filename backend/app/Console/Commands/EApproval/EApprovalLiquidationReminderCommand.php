<?php

declare(strict_types=1);

namespace App\Console\Commands\EApproval;

use App\Models\Tenant;
use App\Modules\EApproval\Services\EApprovalFinanceStageService;
use Illuminate\Console\Command;

class EApprovalLiquidationReminderCommand extends Command
{
    protected $signature = 'e-approval:liquidation-reminders
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Remind requestors and Finance when a released cash advance is due for liquidation.';

    public function handle(EApprovalFinanceStageService $finance): int
    {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $reminders = 0;
        $overdue = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($finance, &$reminders, &$overdue): void {
                $result = $finance->remind();
                $reminders += $result['reminders'];
                $overdue += $result['overdue'];
            });
        }

        $this->info("Liquidation reminders complete. {$reminders} reminder(s), {$overdue} marked overdue.");

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
