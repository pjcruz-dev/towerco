<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\EApproval\Models\EApprovalReportDefinition;
use App\Modules\EApproval\Services\EApprovalReportService;
use App\Modules\Tenancy\Support\TenantEnabledModulesResolver;
use Illuminate\Console\Command;

final class EApprovalReportsRunScheduledCommand extends Command
{
    protected $signature = 'e-approval:reports-run-scheduled
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
        {--force : Ignore schedule window and run all enabled schedules}
    ';

    protected $description = 'Run due scheduled E-Approval saved reports and record export history.';

    public function handle(
        EApprovalReportService $reports,
        TenantEnabledModulesResolver $modules,
    ): int {
        $tenantIds = $this->resolveTenantIds();
        if ($tenantIds === []) {
            $this->error('No tenant found.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $ranTotal = 0;

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if ($tenant === null) {
                continue;
            }

            $tenant->run(function () use ($reports, $modules, $tenant, $force, &$ranTotal): void {
                if (! in_array('e_approval', $modules->resolveForCurrentTenant(), true)) {
                    return;
                }

                $due = $force
                    ? $this->enabledSchedules()
                    : $reports->dueScheduledReports();

                foreach ($due as $report) {
                    try {
                        $result = $reports->runScheduled($report);
                        $ranTotal++;
                        $this->line(sprintf(
                            'Tenant %s: ran report "%s" (%d recipients logged).',
                            $tenant->id,
                            $report->name,
                            count($result['recipients']),
                        ));
                    } catch (\Throwable $e) {
                        $this->warn(sprintf(
                            'Tenant %s: failed report "%s": %s',
                            $tenant->id,
                            $report->name,
                            $e->getMessage(),
                        ));
                    }
                }
            });
        }

        $this->info("Scheduled E-Approval reports complete. {$ranTotal} run(s).");

        return self::SUCCESS;
    }

    /**
     * @return list<EApprovalReportDefinition>
     */
    private function enabledSchedules(): array
    {
        return array_values(array_filter(
            EApprovalReportDefinition::query()->orderBy('name')->get()->all(),
            static function (EApprovalReportDefinition $report): bool {
                $schedule = is_array($report->schedule_json) ? $report->schedule_json : [];

                return ($schedule['enabled'] ?? false) === true;
            },
        ));
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
