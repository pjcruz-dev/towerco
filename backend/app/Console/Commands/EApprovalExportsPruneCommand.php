<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Modules\EApproval\Services\EApprovalReportService;
use Illuminate\Console\Command;

final class EApprovalExportsPruneCommand extends Command
{
    protected $signature = 'e-approval:exports-prune
                            {--domain= : Limit to a single tenant domain}
                            {--tenants=* : Limit to specific tenant IDs}';

    protected $description = 'Delete expired E-Approval async export files from tenant storage';

    public function handle(EApprovalReportService $reports): int
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
            $pruned = $tenant->run(fn (): int => $reports->pruneExpiredExports());
            if ($pruned > 0) {
                $this->info(sprintf('Tenant %s: pruned %d expired export(s).', $tenant->id, $pruned));
            }
            $total += $pruned;
        }

        $this->info(sprintf('Pruned %d expired export file(s).', $total));

        return self::SUCCESS;
    }
}
