<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\Workspace\Services\WorkspaceAuditIndexService;
use Illuminate\Console\Command;

final class WorkspaceAuditPruneCommand extends Command
{
    protected $signature = 'workspace:audit-prune
                            {--days= : Override retention days (default: config toweros.logging.workspace_audit_retention_days)}
                            {--domain= : Limit to a single tenant domain}
                            {--tenants=* : Limit to specific tenant IDs}';

    protected $description = 'Delete workspace audit trail rows older than the configured retention window';

    public function handle(WorkspaceAuditIndexService $audit): int
    {
        $days = (int) ($this->option('days') ?: config('toweros.logging.workspace_audit_retention_days', 365));
        if ($days < 1) {
            $this->error('Retention days must be at least 1.');

            return self::FAILURE;
        }

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
            $pruned = $tenant->run(fn (): int => $audit->pruneOlderThanDays($days));
            if ($pruned > 0) {
                $this->info(sprintf('Tenant %s: pruned %d audit row(s).', $tenant->id, $pruned));
            }
            $total += $pruned;
        }

        $this->info(sprintf('Pruned %d workspace audit row(s) older than %d day(s).', $total, $days));

        return self::SUCCESS;
    }
}
