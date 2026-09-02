<?php

declare(strict_types=1);

namespace App\Console\Commands\DynamicEntities;

use App\Models\Tenant;
use App\Modules\DynamicEntities\Services\DynScheduledTaskService;
use Illuminate\Console\Command;

/**
 * Fleet runner: execute due tenant dyn_scheduled_tasks (Manage Automation & Cron Jobs).
 */
final class DynRunScheduledTasksCommand extends Command
{
    protected $signature = 'dyn:run-scheduled-tasks
        {--domain= : Run for a single tenant domain}
        {--tenants=* : Tenant UUID(s)}
    ';

    protected $description = 'Run due Dynamic Entities scheduled tasks for tenant(s).';

    public function handle(DynScheduledTaskService $service): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($this->option('domain')) {
            $query->whereHas('domains', fn ($q) => $q->where('domain', $this->option('domain')));
        }
        $tenantIds = array_values(array_filter(array_map('strval', (array) $this->option('tenants'))));
        if ($tenantIds !== []) {
            $query->whereIn('id', $tenantIds);
        }

        $total = 0;
        foreach ($query->cursor() as $tenant) {
            /** @var Tenant $tenant */
            $tenant->run(function () use ($service, &$total, $tenant): void {
                $ran = $service->runDue();
                $total += $ran;
                if ($ran > 0) {
                    $this->line(($tenant->id ?? '').': ran '.$ran.' task(s)');
                }
            });
        }

        $this->info('Scheduled tasks completed. Total runs: '.$total);

        return self::SUCCESS;
    }
}
