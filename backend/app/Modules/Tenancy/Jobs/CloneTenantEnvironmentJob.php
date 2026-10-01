<?php

declare(strict_types=1);

namespace App\Modules\Tenancy\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Modules\Tenancy\Services\TenantEnvironmentCloneService;

final class CloneTenantEnvironmentJob extends AbstractQueuedJob
{
    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $cloneId,
    ) {
        parent::__construct();
        $this->timeout = max(60, (int) config('toweros.tenant_database_backup.job_timeout_seconds', 1800));
    }

    public function handle(TenantEnvironmentCloneService $clones): void
    {
        $clones->run($this->cloneId);
    }
}
