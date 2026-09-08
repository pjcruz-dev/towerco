<?php

declare(strict_types=1);

namespace App\Modules\Identity\Jobs;

use App\Core\Jobs\AbstractQueuedJob;
use App\Models\Tenant;
use App\Modules\Identity\Services\EntraOrgDirectoryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Background Microsoft Entra org-chart sync (manager, title, department, license, photos).
 * Runs outside the HTTP request so nginx/gateway 60s timeouts do not abort the sync.
 */
final class SyncEntraOrgDirectoryJob extends AbstractQueuedJob
{
    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly string $tenantId,
        public readonly bool $syncPhotos = true,
    ) {
        parent::__construct();
        $this->onQueue(config('toweros.queues.tenant', config('toweros.queues.default')));
    }

    public function handle(EntraOrgDirectoryService $org): void
    {
        $lockKey = 'entra-org-sync:'.$this->tenantId;
        $lock = Cache::lock($lockKey, 600);
        if (! $lock->get()) {
            Log::info('Entra org sync skipped — already running', ['tenant_id' => $this->tenantId]);

            return;
        }

        try {
            $tenant = Tenant::query()->find($this->tenantId);
            if ($tenant === null) {
                Log::warning('Entra org sync tenant missing', ['tenant_id' => $this->tenantId]);

                return;
            }

            set_time_limit(600);
            ignore_user_abort(true);

            $tenant->run(function () use ($org): void {
                $result = $org->syncDirectoryFromApp(500, $this->syncPhotos);
                Log::info('Entra org sync finished', [
                    'tenant_id' => $this->tenantId,
                    'ok' => $result['ok'] ?? false,
                    'code' => $result['code'] ?? null,
                    'scanned' => $result['scanned'] ?? 0,
                    'updated' => $result['updated'] ?? 0,
                ]);
                Cache::put(
                    'entra-org-sync-result:'.$this->tenantId,
                    $result + ['finished_at' => now()->toIso8601String()],
                    now()->addHour(),
                );
            });
        } catch (\Throwable $exception) {
            Log::error('Entra org sync job failed', [
                'tenant_id' => $this->tenantId,
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
            ]);
            Cache::put(
                'entra-org-sync-result:'.$this->tenantId,
                [
                    'ok' => false,
                    'code' => 'sync_failed',
                    'message' => 'Organization sync failed: '.$exception->getMessage(),
                    'scanned' => 0,
                    'updated' => 0,
                    'managers_linked' => 0,
                    'skipped_unlicensed' => 0,
                    'finished_at' => now()->toIso8601String(),
                ],
                now()->addHour(),
            );
        } finally {
            $lock->release();
        }
    }
}
