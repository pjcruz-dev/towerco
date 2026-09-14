<?php

declare(strict_types=1);

namespace App\Console\Commands\DocExtract;

use App\Models\Tenant;
use App\Modules\DocExtract\Services\DocExtractRetentionService;
use Illuminate\Console\Command;

final class DocExtractPruneCommand extends Command
{
    protected $signature = 'doc-extract:prune
                            {--days= : Override retention days (default from config)}
                            {--domain= : Limit to a single tenant domain}
                            {--tenants=* : Limit to specific tenant IDs}';

    protected $description = 'Delete DocExtract binaries older than retention days; keep original filenames';

    public function handle(DocExtractRetentionService $retention): int
    {
        $daysOption = $this->option('days');
        $days = is_numeric($daysOption) ? (int) $daysOption : null;

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
            $pruned = $tenant->run(fn (): int => $retention->pruneExpired($days));
            if ($pruned > 0) {
                $this->info(sprintf('Tenant %s: pruned %d DocExtract file(s).', $tenant->id, $pruned));
            }
            $total += $pruned;
        }

        $this->info(sprintf('Pruned %d DocExtract file(s).', $total));

        return self::SUCCESS;
    }
}
