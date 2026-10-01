<?php

declare(strict_types=1);

namespace App\Modules\DocExtract\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use Illuminate\Support\Facades\Log;

/**
 * Remap stored OCR text onto the batch schema without re-running OCR.
 * Used after extractor improvements so large ready batches do not block HTTP.
 */
final class RemapDocExtractBatchJob extends AbstractQueuedJob
{
    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $batchId,
    ) {
        parent::__construct();
        $this->onQueue(config('toweros.queues.default'));
    }

    public function handle(DocExtractBatchService $batches): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        $tenant->run(function () use ($batches): void {
            try {
                $batches->remapBatchFieldValuesNow(
                    batchId: $this->batchId,
                    tenantId: $this->tenantId,
                );
            } catch (\Throwable $exception) {
                Log::warning('DocExtract batch remap job failed', [
                    'batch_id' => $this->batchId,
                    'message' => $exception->getMessage(),
                ]);
                throw $exception;
            }
        });
    }
}
