<?php

declare(strict_types=1);

namespace App\Console\Commands\DocExtract;

use App\Models\Tenant;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use Illuminate\Console\Command;

final class DocExtractRequeueCommand extends Command
{
    protected $signature = 'doc-extract:requeue-stuck
        {--tenant= : Tenant UUID (required when not inside tenancy)}
        {--batch= : Limit to one batch UUID}';

    protected $description = 'Re-queue DocExtract documents stuck in pending/scanning after Redis job loss';

    public function handle(DocExtractBatchService $batches): int
    {
        $tenantId = (string) ($this->option('tenant') ?? '');
        $batchId = (string) ($this->option('batch') ?? '');

        if ($tenantId === '') {
            $this->error('Pass --tenant=<uuid>.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $result = $tenant->run(function () use ($batches, $batchId, $tenantId): array {
            return $batches->requeueStuckDocuments(
                batchId: $batchId !== '' ? $batchId : null,
                tenantId: $tenantId,
            );
        });

        $this->info(sprintf(
            'Requeued %d document(s) across %d batch(es).',
            $result['requeued'],
            $result['batches'],
        ));

        return self::SUCCESS;
    }
}
