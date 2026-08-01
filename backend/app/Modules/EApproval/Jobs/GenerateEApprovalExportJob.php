<?php

declare(strict_types=1);

namespace App\Modules\EApproval\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\EApproval\Services\EApprovalReportService;
use Illuminate\Support\Facades\Log;

final class GenerateEApprovalExportJob extends AbstractQueuedJob
{
    public int $timeout = 600;

    public int $tries = 2;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $historyId,
    ) {
        parent::__construct();
        $this->onQueue(config('toweros.queues.tenant', config('toweros.queues.default')));
    }

    public function handle(EApprovalReportService $reports): void
    {
        $tenant = Tenant::query()->find($this->tenantId);
        if ($tenant === null) {
            Log::warning('e_approval.async_export.tenant_missing', [
                'tenant_id' => $this->tenantId,
                'history_id' => $this->historyId,
            ]);

            return;
        }

        $tenant->run(function () use ($reports): void {
            $reports->processQueuedHistory($this->historyId);
        });
    }
}
