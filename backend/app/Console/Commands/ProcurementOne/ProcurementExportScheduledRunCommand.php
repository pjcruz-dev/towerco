<?php

declare(strict_types=1);

namespace App\Console\Commands\ProcurementOne;

use App\Models\Tenant;
use App\Modules\ProcurementOne\Services\ProcurementExportScheduleService;
use App\Modules\ProcurementOne\Services\ProcurementOnePlanFeaturesService;
use App\Modules\ProcurementOne\Services\ProcurementScheduledExportMailService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

final class ProcurementExportScheduledRunCommand extends Command
{
    protected $signature = 'procurement:export-run-scheduled
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
        {--force : Ignore schedule window and send now}
    ';

    protected $description = 'Email scheduled procurement Excel pack exports to finance recipients.';

    public function handle(
        ProcurementExportScheduleService $schedule,
        ProcurementScheduledExportMailService $mailer,
        ProcurementOnePlanFeaturesService $planFeatures,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $sentTotal = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($schedule, $mailer, $planFeatures, $modules, $tenant, $force, &$sentTotal): void {
                if (! in_array('procurement_one', $modules->resolveForCurrentTenant(), true)) {
                    return;
                }

                if (! $planFeatures->reportingExportsEnabled()) {
                    return;
                }

                if (! $force && ! $schedule->shouldRunNow()) {
                    return;
                }

                $result = $mailer->sendScheduledExport();
                $sentTotal += $result['sent'];

                $this->line(sprintf(
                    'Tenant %s: emailed %d recipient(s) for %s (%s).',
                    $tenant->id,
                    $result['sent'],
                    $result['period_label'],
                    $result['filename'],
                ));
            });
        }

        $this->info("Scheduled procurement export complete. {$sentTotal} email(s) sent.");

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
