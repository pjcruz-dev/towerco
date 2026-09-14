<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\DocExtract\Services\DocExtractBatchService;
use App\Modules\Identity\Services\ModuleListExportService;
use App\Modules\Ticketing\Services\TicketingTicketService;
use Illuminate\Support\Facades\Log;

final class ProcessModuleListExportJob extends AbstractQueuedJob
{
    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $exportId,
    ) {
        parent::__construct();
        $this->onQueue(config('toweros.queues.tenant', config('toweros.queues.default')));
    }

    public function handle(
        ModuleListExportService $exports,
        DocExtractBatchService $batches,
        TicketingTicketService $tickets,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            Log::warning('module_list.async_export.tenant_missing', [
                'tenant_id' => $this->tenantId,
                'export_id' => $this->exportId,
            ]);

            return;
        }

        $tenant->run(function () use ($exports, $batches, $tickets): void {
            $exports->process($this->exportId, $batches, $tickets);
        });
    }
}
