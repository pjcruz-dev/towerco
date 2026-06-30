<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementRfqReminderService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

final class ProcurementRfqReminderCommand extends Command
{
    protected $signature = 'procurement:rfq-reminders
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Send RFQ bidding reminder emails to invited vendors before close.';

    public function handle(
        ProcurementRfqReminderService $service,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();

        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $totalReminders = 0;
        $totalRfqs = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($service, $modules, $tenant, &$totalReminders, &$totalRfqs): void {
                if (! in_array('procurement_one', $modules->resolveForCurrentTenant(), true)) {
                    return;
                }

                $result = $service->run();
                $totalReminders += $result['reminders_sent'];
                $totalRfqs += $result['rfqs_scanned'];

                if ($result['reminders_sent'] > 0) {
                    $this->line(sprintf(
                        'Tenant %s: %d reminder(s) across %d open RFQ(s).',
                        $tenant->id,
                        $result['reminders_sent'],
                        $result['rfqs_scanned'],
                    ));
                }
            });
        }

        $this->info("RFQ reminders complete. {$totalReminders} email(s), {$totalRfqs} RFQ(s) scanned.");

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

            return $tenant !== null ? [(string) $tenant->id] : [];
        }

        return Tenant::query()->pluck('id')->map(static fn ($id) => (string) $id)->all();
    }
}
